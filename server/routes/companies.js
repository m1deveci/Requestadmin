import express from 'express';
import { body, validationResult } from 'express-validator';
import pool from '../config/database.js';
import { requireAdmin } from '../middleware/auth.js';

const router = express.Router();

// Tüm şirketleri getir (sadece admin)
router.get('/', requireAdmin, async (req, res) => {
  try {
    const [companies] = await pool.execute(`
      SELECT 
        c.*,
        COUNT(u.id) as user_count
      FROM companies c
      LEFT JOIN users u ON c.id = u.company_id
      GROUP BY c.id
      ORDER BY c.created_at DESC
    `);

    res.json(companies);
  } catch (error) {
    console.error('Şirketleri getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Tek şirket getir
router.get('/:id', requireAdmin, async (req, res) => {
  try {
    const { id } = req.params;

    const [companies] = await pool.execute(
      'SELECT * FROM companies WHERE id = ?',
      [id]
    );

    if (companies.length === 0) {
      return res.status(404).json({ message: 'Şirket bulunamadı' });
    }

    res.json(companies[0]);
  } catch (error) {
    console.error('Şirket getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Şirket durumunu güncelle
router.patch('/:id/status', [
  body('status').isIn(['pending', 'approved', 'rejected'])
], requireAdmin, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz durum',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const { status } = req.body;

    // Şirket kontrolü
    const [companies] = await pool.execute(
      'SELECT id FROM companies WHERE id = ?',
      [id]
    );

    if (companies.length === 0) {
      return res.status(404).json({ message: 'Şirket bulunamadı' });
    }

    await pool.execute(
      'UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?',
      [status, id]
    );

    res.json({ message: 'Şirket durumu güncellendi' });
  } catch (error) {
    console.error('Şirket durumu güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Şirket güncelle
router.put('/:id', [
  body('companyName').isLength({ min: 2, max: 255 }),
  body('phone').isLength({ min: 10 }),
  body('authorizedPerson').isLength({ min: 2 }),
  body('taxNumber').isLength({ min: 10 }),
  body('address').isLength({ min: 10 }),
  body('email').isEmail().normalizeEmail()
], requireAdmin, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz şirket bilgileri',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const {
      companyName,
      phone,
      authorizedPerson,
      taxNumber,
      address,
      email,
      logo
    } = req.body;

    // Şirket kontrolü
    const [companies] = await pool.execute(
      'SELECT id FROM companies WHERE id = ?',
      [id]
    );

    if (companies.length === 0) {
      return res.status(404).json({ message: 'Şirket bulunamadı' });
    }

    // Email kontrolü
    const [existingCompanies] = await pool.execute(
      'SELECT id FROM companies WHERE email = ? AND id != ?',
      [email, id]
    );

    if (existingCompanies.length > 0) {
      return res.status(400).json({ message: 'Bu email adresi zaten kullanılıyor' });
    }

    // Tax number kontrolü
    const [existingTax] = await pool.execute(
      'SELECT id FROM companies WHERE tax_number = ? AND id != ?',
      [taxNumber, id]
    );

    if (existingTax.length > 0) {
      return res.status(400).json({ message: 'Bu vergi numarası zaten kullanılıyor' });
    }

    await pool.execute(`
      UPDATE companies SET 
        company_name = ?, phone = ?, authorized_person = ?, tax_number = ?,
        address = ?, email = ?, logo = ?, updated_at = NOW()
      WHERE id = ?
    `, [companyName, phone, authorizedPerson, taxNumber, address, email, logo || null, id]);

    res.json({ message: 'Şirket başarıyla güncellendi' });
  } catch (error) {
    console.error('Şirket güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Şirket sil
router.delete('/:id', requireAdmin, async (req, res) => {
  try {
    const { id } = req.params;

    // Şirket kontrolü
    const [companies] = await pool.execute(
      'SELECT id FROM companies WHERE id = ?',
      [id]
    );

    if (companies.length === 0) {
      return res.status(404).json({ message: 'Şirket bulunamadı' });
    }

    // Kullanıcı kontrolü
    const [users] = await pool.execute(
      'SELECT id FROM users WHERE company_id = ? LIMIT 1',
      [id]
    );

    if (users.length > 0) {
      return res.status(400).json({ message: 'Bu şirketin kullanıcıları olduğu için silinemez' });
    }

    await pool.execute(
      'DELETE FROM companies WHERE id = ?',
      [id]
    );

    res.json({ message: 'Şirket başarıyla silindi' });
  } catch (error) {
    console.error('Şirket silme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

export default router;


