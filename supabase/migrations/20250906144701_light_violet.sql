/*
  # Create request status history table

  1. New Tables
    - `request_status_history`
      - `id` (uuid, primary key)
      - `request_id` (uuid, foreign key to requests)
      - `old_status` (text, nullable)
      - `new_status` (text)
      - `changed_by` (uuid, foreign key to users)
      - `notes` (text, nullable)
      - `created_at` (timestamp)

  2. Security
    - Enable RLS on `request_status_history` table
    - Add policies for tracking access
*/

CREATE TABLE IF NOT EXISTS request_status_history (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  request_id uuid NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
  old_status text,
  new_status text NOT NULL,
  changed_by uuid NOT NULL REFERENCES users(id),
  notes text,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE request_status_history ENABLE ROW LEVEL SECURITY;

-- Users can see history of requests they have access to
CREATE POLICY "Users can see request history"
  ON request_status_history
  FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM requests r
      WHERE r.id = request_status_history.request_id
      AND (
        r.employee_id = auth.uid()
        OR r.assigned_to = auth.uid()
        OR EXISTS (
          SELECT 1 FROM users u
          WHERE u.id = r.employee_id
          AND u.company_id::text = (auth.jwt() -> 'user_metadata' ->> 'company_id')
          AND (auth.jwt() -> 'user_metadata' ->> 'role') IN ('hr', 'admin')
        )
        OR EXISTS (
          SELECT 1 FROM auth.users 
          WHERE auth.users.id = auth.uid() 
          AND auth.users.raw_user_meta_data->>'role' = 'admin'
        )
      )
    )
  );

-- Function to log status changes
CREATE OR REPLACE FUNCTION log_request_status_change()
RETURNS trigger AS $$
BEGIN
  IF OLD.status IS DISTINCT FROM NEW.status THEN
    INSERT INTO request_status_history (
      request_id,
      old_status,
      new_status,
      changed_by
    ) VALUES (
      NEW.id,
      OLD.status,
      NEW.status,
      auth.uid()
    );
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Trigger to log status changes
CREATE OR REPLACE TRIGGER log_request_status_change_trigger
  AFTER UPDATE ON requests
  FOR EACH ROW EXECUTE FUNCTION log_request_status_change();