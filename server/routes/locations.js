import express from 'express';
import { body, validationResult } from 'express-validator';
import pool from '../config/database.js';
import { requireHR } from '../middleware/auth.js';

const router = express.Router();

// Şirket lokasyonlarını getir
router.get('/', requireHR, async (req, res) => {
  try {
    const [locations] = await pool.execute(`
      SELECT 
        l.*,
        p.province_name
      FROM locations l
      LEFT JOIN provinces p ON l.province_id = p.id
      WHERE l.company_id = ?
      ORDER BY l.location_name ASC
    `, [req.user.company_id]);

    res.json(locations);
  } catch (error) {
    console.error('Lokasyonları getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Tüm illeri getir
router.get('/provinces', requireHR, async (req, res) => {
  try {
    const [provinces] = await pool.execute(
      'SELECT * FROM provinces ORDER BY province_name ASC'
    );

    res.json(provinces);
  } catch (error) {
    console.error('İlleri getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Yeni lokasyon oluştur
router.post('/', [
  body('locationName').isLength({ min: 2, max: 255 }),
  body('address').optional().isLength({ max: 500 }),
  body('provinceId').optional().isUUID()
], requireHR, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz lokasyon bilgileri',
        errors: errors.array()
      });
    }

    const { locationName, address, provinceId } = req.body;

    // İl kontrolü
    if (provinceId) {
      const [provinces] = await pool.execute(
        'SELECT id FROM provinces WHERE id = ?',
        [provinceId]
      );

      if (provinces.length === 0) {
        return res.status(400).json({ message: 'Geçersiz il' });
      }
    }

    const [result] = await pool.execute(`
      INSERT INTO locations (company_id, location_name, address, province_id)
      VALUES (?, ?, ?, ?)
    `, [req.user.company_id, locationName, address || null, provinceId || null]);

    res.status(201).json({
      message: 'Lokasyon başarıyla oluşturuldu',
      locationId: result.insertId
    });
  } catch (error) {
    console.error('Lokasyon oluşturma hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Lokasyon güncelle
router.put('/:id', [
  body('locationName').isLength({ min: 2, max: 255 }),
  body('address').optional().isLength({ max: 500 }),
  body('provinceId').optional().isUUID()
], requireHR, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz lokasyon bilgileri',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const { locationName, address, provinceId } = req.body;

    // Lokasyon kontrolü
    const [locations] = await pool.execute(
      'SELECT company_id FROM locations WHERE id = ?',
      [id]
    );

    if (locations.length === 0) {
      return res.status(404).json({ message: 'Lokasyon bulunamadı' });
    }

    // HR sadece kendi şirketinin lokasyonlarını güncelleyebilir
    if (locations[0].company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu lokasyonu güncelleme yetkiniz yok' });
    }

    // İl kontrolü
    if (provinceId) {
      const [provinces] = await pool.execute(
        'SELECT id FROM provinces WHERE id = ?',
        [provinceId]
      );

      if (provinces.length === 0) {
        return res.status(400).json({ message: 'Geçersiz il' });
      }
    }

    await pool.execute(`
      UPDATE locations SET 
        location_name = ?, address = ?, province_id = ?
      WHERE id = ?
    `, [locationName, address || null, provinceId || null, id]);

    res.json({ message: 'Lokasyon başarıyla güncellendi' });
  } catch (error) {
    console.error('Lokasyon güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Lokasyon sil
router.delete('/:id', requireHR, async (req, res) => {
  try {
    const { id } = req.params;

    // Lokasyon kontrolü
    const [locations] = await pool.execute(
      'SELECT company_id FROM locations WHERE id = ?',
      [id]
    );

    if (locations.length === 0) {
      return res.status(404).json({ message: 'Lokasyon bulunamadı' });
    }

    // HR sadece kendi şirketinin lokasyonlarını silebilir
    if (locations[0].company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu lokasyonu silme yetkiniz yok' });
    }

    // Kullanıcı kontrolü
    const [users] = await pool.execute(
      'SELECT id FROM users WHERE location_id = ? LIMIT 1',
      [id]
    );

    if (users.length > 0) {
      return res.status(400).json({ message: 'Bu lokasyonda kullanıcılar olduğu için silinemez' });
    }

    await pool.execute(
      'DELETE FROM locations WHERE id = ?',
      [id]
    );

    res.json({ message: 'Lokasyon başarıyla silindi' });
  } catch (error) {
    console.error('Lokasyon silme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

export default router;


