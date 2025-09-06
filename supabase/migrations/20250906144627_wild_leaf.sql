/*
  # Create locations table

  1. New Tables
    - `locations`
      - `id` (uuid, primary key)
      - `company_id` (uuid, foreign key to companies)
      - `province_id` (uuid, foreign key to provinces, nullable)
      - `location_name` (text)
      - `address` (text, nullable)
      - `created_at` (timestamp)

  2. Security
    - Enable RLS on `locations` table
    - Add policies for company-based access
*/

CREATE TABLE IF NOT EXISTS locations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  company_id uuid NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  province_id uuid REFERENCES provinces(id),
  location_name text NOT NULL,
  address text,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE locations ENABLE ROW LEVEL SECURITY;

-- Users can only see locations from their company
CREATE POLICY "Users can see company locations"
  ON locations
  FOR SELECT
  TO authenticated
  USING (
    company_id::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
    OR EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );

-- HR and Admin can manage locations
CREATE POLICY "HR and Admin can manage locations"
  ON locations
  FOR ALL
  TO authenticated
  USING (
    (company_id::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
     AND (auth.jwt() -> 'user_metadata' ->> 'role') IN ('hr', 'admin'))
    OR EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );