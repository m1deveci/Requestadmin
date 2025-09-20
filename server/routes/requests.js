import express from 'express';
import { body, validationResult } from 'express-validator';
import pool from '../config/database.js';
import { requireHR, requireEmployee } from '../middleware/auth.js';

const router = express.Router();

// Tüm talepleri getir (HR ve Admin için)
router.get('/', requireHR, async (req, res) => {
  try {
    let query = `
      SELECT 
        r.*,
        u.first_name as employee_first_name,
        u.last_name as employee_last_name,
        u.email as employee_email,
        rc.category_name,
        a.first_name as assigned_first_name,
        a.last_name as assigned_last_name
      FROM requests r
      LEFT JOIN users u ON r.employee_id = u.id
      LEFT JOIN request_categories rc ON r.category_id = rc.id
      LEFT JOIN users a ON r.assigned_to = a.id
    `;

    const params = [];

    // HR sadece kendi şirketinin taleplerini görebilir
    if (req.user.role === 'hr') {
      query += ' WHERE u.company_id = ?';
      params.push(req.user.company_id);
    }

    query += ' ORDER BY r.created_at DESC';

    const [requests] = await pool.execute(query, params);

    res.json(requests);
  } catch (error) {
    console.error('Talepleri getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Kullanıcının kendi taleplerini getir
router.get('/my-requests', requireEmployee, async (req, res) => {
  try {
    const [requests] = await pool.execute(`
      SELECT 
        r.*,
        rc.category_name,
        a.first_name as assigned_first_name,
        a.last_name as assigned_last_name
      FROM requests r
      LEFT JOIN request_categories rc ON r.category_id = rc.id
      LEFT JOIN users a ON r.assigned_to = a.id
      WHERE r.employee_id = ?
      ORDER BY r.created_at DESC
    `, [req.user.id]);

    res.json(requests);
  } catch (error) {
    console.error('Kullanıcı taleplerini getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Tek talep getir
router.get('/:id', requireEmployee, async (req, res) => {
  try {
    const { id } = req.params;

    const [requests] = await pool.execute(`
      SELECT 
        r.*,
        u.first_name as employee_first_name,
        u.last_name as employee_last_name,
        u.email as employee_email,
        rc.category_name,
        a.first_name as assigned_first_name,
        a.last_name as assigned_last_name
      FROM requests r
      LEFT JOIN users u ON r.employee_id = u.id
      LEFT JOIN request_categories rc ON r.category_id = rc.id
      LEFT JOIN users a ON r.assigned_to = a.id
      WHERE r.id = ?
    `, [id]);

    if (requests.length === 0) {
      return res.status(404).json({ message: 'Talep bulunamadı' });
    }

    const request = requests[0];

    // Kullanıcı sadece kendi taleplerini veya atandığı talepleri görebilir
    if (req.user.role === 'employee' && 
        request.employee_id !== req.user.id && 
        request.assigned_to !== req.user.id) {
      return res.status(403).json({ message: 'Bu talebi görme yetkiniz yok' });
    }

    // HR sadece kendi şirketinin taleplerini görebilir
    if (req.user.role === 'hr' && request.employee_company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu talebi görme yetkiniz yok' });
    }

    res.json(request);
  } catch (error) {
    console.error('Talep getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Yeni talep oluştur
router.post('/', [
  body('categoryId').isUUID(),
  body('title').isLength({ min: 5, max: 255 }),
  body('description').isLength({ min: 10 }),
  body('priority').isIn(['low', 'medium', 'high'])
], requireEmployee, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz talep bilgileri',
        errors: errors.array()
      });
    }

    const { categoryId, title, description, priority, attachment } = req.body;

    // Kategori kontrolü
    const [categories] = await pool.execute(
      'SELECT id FROM request_categories WHERE id = ?',
      [categoryId]
    );

    if (categories.length === 0) {
      return res.status(400).json({ message: 'Geçersiz kategori' });
    }

    // Talep numarası oluştur
    const year = new Date().getFullYear();
    const [lastRequest] = await pool.execute(
      'SELECT request_number FROM requests WHERE request_number LIKE ? ORDER BY request_number DESC LIMIT 1',
      [`${year}-%`]
    );

    let requestNumber;
    if (lastRequest.length === 0) {
      requestNumber = `${year}-000001`;
    } else {
      const lastNumber = parseInt(lastRequest[0].request_number.split('-')[1]);
      requestNumber = `${year}-${String(lastNumber + 1).padStart(6, '0')}`;
    }

    // Talep oluştur
    const [result] = await pool.execute(`
      INSERT INTO requests (request_number, employee_id, category_id, title, description, attachment, priority)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `, [requestNumber, req.user.id, categoryId, title, description, attachment, priority]);

    // Status history ekle
    await pool.execute(`
      INSERT INTO request_status_history (request_id, new_status, changed_by)
      VALUES (?, 'pending', ?)
    `, [result.insertId, req.user.id]);

    res.status(201).json({
      message: 'Talep başarıyla oluşturuldu',
      requestId: result.insertId,
      requestNumber
    });
  } catch (error) {
    console.error('Talep oluşturma hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Talep durumunu güncelle
router.patch('/:id/status', [
  body('status').isIn(['pending', 'assigned', 'in_progress', 'manager_approval', 'approved', 'rejected', 'completed', 'cancelled']),
  body('notes').optional().isLength({ max: 500 })
], requireHR, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz durum bilgileri',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const { status, notes, assignedTo } = req.body;

    // Talep kontrolü
    const [requests] = await pool.execute(
      'SELECT * FROM requests WHERE id = ?',
      [id]
    );

    if (requests.length === 0) {
      return res.status(404).json({ message: 'Talep bulunamadı' });
    }

    const request = requests[0];

    // HR sadece kendi şirketinin taleplerini güncelleyebilir
    const [employee] = await pool.execute(
      'SELECT company_id FROM users WHERE id = ?',
      [request.employee_id]
    );

    if (employee[0].company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu talebi güncelleme yetkiniz yok' });
    }

    // Durumu güncelle
    await pool.execute(`
      UPDATE requests 
      SET status = ?, assigned_to = ?, updated_at = NOW(), completed_at = ?
      WHERE id = ?
    `, [
      status, 
      assignedTo || null, 
      status === 'completed' ? new Date() : null,
      id
    ]);

    // Status history ekle
    await pool.execute(`
      INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, notes)
      VALUES (?, ?, ?, ?, ?)
    `, [id, request.status, status, req.user.id, notes || null]);

    res.json({ message: 'Talep durumu güncellendi' });
  } catch (error) {
    console.error('Talep durumu güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Talep geçmişini getir
router.get('/:id/history', requireEmployee, async (req, res) => {
  try {
    const { id } = req.params;

    // Talep kontrolü
    const [requests] = await pool.execute(
      'SELECT employee_id FROM requests WHERE id = ?',
      [id]
    );

    if (requests.length === 0) {
      return res.status(404).json({ message: 'Talep bulunamadı' });
    }

    // Yetki kontrolü
    if (req.user.role === 'employee' && requests[0].employee_id !== req.user.id) {
      return res.status(403).json({ message: 'Bu talebin geçmişini görme yetkiniz yok' });
    }

    const [history] = await pool.execute(`
      SELECT 
        rsh.*,
        u.first_name,
        u.last_name
      FROM request_status_history rsh
      LEFT JOIN users u ON rsh.changed_by = u.id
      WHERE rsh.request_id = ?
      ORDER BY rsh.created_at ASC
    `, [id]);

    res.json(history);
  } catch (error) {
    console.error('Talep geçmişi getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

export default router;


