/*
  # Create requests table

  1. New Tables
    - `requests`
      - `id` (uuid, primary key)
      - `request_number` (text, unique)
      - `employee_id` (uuid, foreign key to users)
      - `category_id` (uuid, foreign key to request_categories)
      - `title` (text)
      - `description` (text)
      - `attachment` (text, nullable)
      - `status` (enum)
      - `assigned_to` (uuid, foreign key to users, nullable)
      - `priority` (enum: low, medium, high)
      - `created_at` (timestamp)
      - `updated_at` (timestamp)
      - `completed_at` (timestamp, nullable)

  2. Security
    - Enable RLS on `requests` table
    - Add policies for role-based access
*/

CREATE TABLE IF NOT EXISTS requests (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  request_number text UNIQUE NOT NULL,
  employee_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  category_id uuid NOT NULL REFERENCES request_categories(id),
  title text NOT NULL,
  description text NOT NULL,
  attachment text,
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'assigned', 'in_progress', 'manager_approval', 'approved', 'rejected', 'completed', 'cancelled')),
  assigned_to uuid REFERENCES users(id),
  priority text DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now(),
  completed_at timestamptz
);

ALTER TABLE requests ENABLE ROW LEVEL SECURITY;

-- Employees can see their own requests
CREATE POLICY "Employees can see own requests"
  ON requests
  FOR SELECT
  TO authenticated
  USING (
    employee_id = auth.uid()
    OR assigned_to = auth.uid()
  );

-- Employees can create requests
CREATE POLICY "Employees can create requests"
  ON requests
  FOR INSERT
  TO authenticated
  WITH CHECK (employee_id = auth.uid());

-- HR can see requests from their company
CREATE POLICY "HR can see company requests"
  ON requests
  FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users u
      WHERE u.id = requests.employee_id
      AND u.company_id::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
      AND (auth.jwt() -> 'user_metadata' ->> 'role') = 'hr'
    )
  );

-- HR can manage requests from their company
CREATE POLICY "HR can manage company requests"
  ON requests
  FOR ALL
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users u
      WHERE u.id = requests.employee_id
      AND u.company_id::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
      AND (auth.jwt() -> 'user_metadata' ->> 'role') = 'hr'
    )
  );

-- Admin can see all requests
CREATE POLICY "Admin can see all requests"
  ON requests
  FOR ALL
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );

-- Function to generate request number
CREATE OR REPLACE FUNCTION generate_request_number()
RETURNS text AS $$
DECLARE
  year_part text;
  sequence_part text;
  next_number integer;
BEGIN
  year_part := EXTRACT(YEAR FROM NOW())::text;
  
  SELECT COALESCE(MAX(CAST(SUBSTRING(request_number FROM 6) AS integer)), 0) + 1
  INTO next_number
  FROM requests
  WHERE request_number LIKE year_part || '-%';
  
  sequence_part := LPAD(next_number::text, 6, '0');
  
  RETURN year_part || '-' || sequence_part;
END;
$$ LANGUAGE plpgsql;

-- Trigger to auto-generate request number
CREATE OR REPLACE FUNCTION set_request_number()
RETURNS trigger AS $$
BEGIN
  IF NEW.request_number IS NULL OR NEW.request_number = '' THEN
    NEW.request_number := generate_request_number();
  END IF;
  NEW.updated_at := now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE TRIGGER set_request_number_trigger
  BEFORE INSERT OR UPDATE ON requests
  FOR EACH ROW EXECUTE FUNCTION set_request_number();