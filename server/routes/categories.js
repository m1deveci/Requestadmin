import express from 'express';
import { body, validationResult } from 'express-validator';
import pool from '../config/database.js';
import { requireAdmin, requireHR } from '../middleware/auth.js';

const router = express.Router();

// Tüm kategorileri getir
router.get('/', requireHR, async (req, res) => {
  try {
    const [categories] = await pool.execute(
      'SELECT * FROM request_categories ORDER BY category_name ASC'
    );

    res.json(categories);
  } catch (error) {
    console.error('Kategorileri getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Yeni kategori oluştur
router.post('/', [
  body('categoryName').isLength({ min: 2, max: 100 }),
  body('requiresManagerApproval').isBoolean()
], requireAdmin, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz kategori bilgileri',
        errors: errors.array()
      });
    }

    const { categoryName, requiresManagerApproval } = req.body;

    // Kategori adı kontrolü
    const [existing] = await pool.execute(
      'SELECT id FROM request_categories WHERE category_name = ?',
      [categoryName]
    );

    if (existing.length > 0) {
      return res.status(400).json({ message: 'Bu kategori adı zaten mevcut' });
    }

    const [result] = await pool.execute(
      'INSERT INTO request_categories (category_name, requires_manager_approval) VALUES (?, ?)',
      [categoryName, requiresManagerApproval]
    );

    res.status(201).json({
      message: 'Kategori başarıyla oluşturuldu',
      categoryId: result.insertId
    });
  } catch (error) {
    console.error('Kategori oluşturma hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Kategori güncelle
router.put('/:id', [
  body('categoryName').isLength({ min: 2, max: 100 }),
  body('requiresManagerApproval').isBoolean()
], requireAdmin, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz kategori bilgileri',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const { categoryName, requiresManagerApproval } = req.body;

    // Kategori kontrolü
    const [categories] = await pool.execute(
      'SELECT id FROM request_categories WHERE id = ?',
      [id]
    );

    if (categories.length === 0) {
      return res.status(404).json({ message: 'Kategori bulunamadı' });
    }

    // Kategori adı kontrolü
    const [existing] = await pool.execute(
      'SELECT id FROM request_categories WHERE category_name = ? AND id != ?',
      [categoryName, id]
    );

    if (existing.length > 0) {
      return res.status(400).json({ message: 'Bu kategori adı zaten mevcut' });
    }

    await pool.execute(
      'UPDATE request_categories SET category_name = ?, requires_manager_approval = ? WHERE id = ?',
      [categoryName, requiresManagerApproval, id]
    );

    res.json({ message: 'Kategori başarıyla güncellendi' });
  } catch (error) {
    console.error('Kategori güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Kategori sil
router.delete('/:id', requireAdmin, async (req, res) => {
  try {
    const { id } = req.params;

    // Kategori kontrolü
    const [categories] = await pool.execute(
      'SELECT id FROM request_categories WHERE id = ?',
      [id]
    );

    if (categories.length === 0) {
      return res.status(404).json({ message: 'Kategori bulunamadı' });
    }

    // Kategori kullanım kontrolü
    const [requests] = await pool.execute(
      'SELECT id FROM requests WHERE category_id = ? LIMIT 1',
      [id]
    );

    if (requests.length > 0) {
      return res.status(400).json({ message: 'Bu kategori kullanıldığı için silinemez' });
    }

    await pool.execute(
      'DELETE FROM request_categories WHERE id = ?',
      [id]
    );

    res.json({ message: 'Kategori başarıyla silindi' });
  } catch (error) {
    console.error('Kategori silme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

export default router;


