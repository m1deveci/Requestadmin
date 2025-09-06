/*
  # Create companies table

  1. New Tables
    - `companies`
      - `id` (uuid, primary key)
      - `company_name` (text, unique)
      - `phone` (text)
      - `authorized_person` (text)
      - `tax_number` (text, unique)
      - `address` (text)
      - `logo` (text, nullable)
      - `email` (text, unique)
      - `status` (enum: pending, approved, rejected)
      - `created_at` (timestamp)
      - `updated_at` (timestamp)

  2. Security
    - Enable RLS on `companies` table
    - Add policies for different user roles
*/

CREATE TABLE IF NOT EXISTS companies (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  company_name text UNIQUE NOT NULL,
  phone text NOT NULL,
  authorized_person text NOT NULL,
  tax_number text UNIQUE NOT NULL,
  address text NOT NULL,
  logo text,
  email text UNIQUE NOT NULL,
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE companies ENABLE ROW LEVEL SECURITY;

-- Admin can see all companies
CREATE POLICY "Admins can manage all companies"
  ON companies
  FOR ALL
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );

-- Companies can see their own data
CREATE POLICY "Companies can read own data"
  ON companies
  FOR SELECT
  TO authenticated
  USING (
    auth.uid()::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
  );

-- Public can insert (for registration)
CREATE POLICY "Anyone can register company"
  ON companies
  FOR INSERT
  TO anon
  WITH CHECK (true);