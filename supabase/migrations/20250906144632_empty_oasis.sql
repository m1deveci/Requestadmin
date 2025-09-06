/*
  # Create users table

  1. New Tables
    - `users`
      - `id` (uuid, primary key, references auth.users)
      - `company_id` (uuid, foreign key to companies)
      - `location_id` (uuid, foreign key to locations, nullable)
      - `province_id` (uuid, foreign key to provinces, nullable)
      - `first_name` (text)
      - `last_name` (text)
      - `email` (text, unique)
      - `role` (enum: admin, hr, employee)
      - `title` (text, nullable)
      - `department` (text, nullable)
      - `manager_id` (uuid, foreign key to users, nullable)
      - `status` (enum: active, inactive)
      - `last_login` (timestamp, nullable)
      - `created_at` (timestamp)
      - `updated_at` (timestamp)

  2. Security
    - Enable RLS on `users` table
    - Add policies for role-based access
*/

CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  company_id uuid NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  location_id uuid REFERENCES locations(id),
  province_id uuid REFERENCES provinces(id),
  first_name text NOT NULL,
  last_name text NOT NULL,
  email text UNIQUE NOT NULL,
  role text DEFAULT 'employee' CHECK (role IN ('admin', 'hr', 'employee')),
  title text,
  department text,
  manager_id uuid REFERENCES users(id),
  status text DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
  last_login timestamptz,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- Users can see their own data
CREATE POLICY "Users can read own data"
  ON users
  FOR SELECT
  TO authenticated
  USING (auth.uid() = id);

-- HR can see users in their company
CREATE POLICY "HR can see company users"
  ON users
  FOR SELECT
  TO authenticated
  USING (
    company_id::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
    AND (auth.jwt() -> 'user_metadata' ->> 'role') = 'hr'
  );

-- Admin can see all users
CREATE POLICY "Admin can see all users"
  ON users
  FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );

-- HR and Admin can manage users
CREATE POLICY "HR and Admin can manage users"
  ON users
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

-- Function to handle user creation
CREATE OR REPLACE FUNCTION handle_new_user()
RETURNS trigger AS $$
BEGIN
  INSERT INTO public.users (
    id,
    company_id,
    location_id,
    province_id,
    first_name,
    last_name,
    email,
    role
  )
  VALUES (
    new.id,
    (new.raw_user_meta_data->>'company_id')::uuid,
    (new.raw_user_meta_data->>'location_id')::uuid,
    (new.raw_user_meta_data->>'province_id')::uuid,
    new.raw_user_meta_data->>'first_name',
    new.raw_user_meta_data->>'last_name',
    new.email,
    COALESCE(new.raw_user_meta_data->>'role', 'employee')
  );
  RETURN new;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Trigger to create user profile on signup
CREATE OR REPLACE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW EXECUTE FUNCTION handle_new_user();