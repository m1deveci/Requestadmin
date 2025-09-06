/*
  # Create request categories table

  1. New Tables
    - `request_categories`
      - `id` (uuid, primary key)
      - `category_name` (text, unique)
      - `requires_manager_approval` (boolean)
      - `created_at` (timestamp)

  2. Security
    - Enable RLS on `request_categories` table
    - Add policies for authenticated users
*/

CREATE TABLE IF NOT EXISTS request_categories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  category_name text UNIQUE NOT NULL,
  requires_manager_approval boolean DEFAULT false,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE request_categories ENABLE ROW LEVEL SECURITY;

-- All authenticated users can read categories
CREATE POLICY "Authenticated users can read categories"
  ON request_categories
  FOR SELECT
  TO authenticated
  USING (true);

-- Only admins can manage categories
CREATE POLICY "Admins can manage categories"
  ON request_categories
  FOR ALL
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );

-- Insert default categories
INSERT INTO request_categories (category_name, requires_manager_approval) VALUES
('İzin Talebi', true),
('Avans Talebi', true),
('Malzeme Talebi', false),
('IT Destek', false),
('İnsan Kaynakları', false),
('Muhasebe', false),
('Genel Talep', false),
('Şikayet', false),
('Öneri', false),
('Eğitim Talebi', true)
ON CONFLICT (category_name) DO NOTHING;