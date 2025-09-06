/*
  # Create provinces table

  1. New Tables
    - `provinces`
      - `id` (uuid, primary key)
      - `province_name` (text, unique)
      - `province_code` (text, unique)
      - `created_at` (timestamp)

  2. Security
    - Enable RLS on `provinces` table
    - Add policy for authenticated users to read
*/

CREATE TABLE IF NOT EXISTS provinces (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  province_name text UNIQUE NOT NULL,
  province_code text UNIQUE NOT NULL,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE provinces ENABLE ROW LEVEL SECURITY;

-- All authenticated users can read provinces
CREATE POLICY "Authenticated users can read provinces"
  ON provinces
  FOR SELECT
  TO authenticated
  USING (true);

-- Only admins can manage provinces
CREATE POLICY "Admins can manage provinces"
  ON provinces
  FOR ALL
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM auth.users 
      WHERE auth.users.id = auth.uid() 
      AND auth.users.raw_user_meta_data->>'role' = 'admin'
    )
  );

-- Insert Turkish provinces
INSERT INTO provinces (province_name, province_code) VALUES
('Adana', '01'),
('Adıyaman', '02'),
('Afyonkarahisar', '03'),
('Ağrı', '04'),
('Amasya', '05'),
('Ankara', '06'),
('Antalya', '07'),
('Artvin', '08'),
('Aydın', '09'),
('Balıkesir', '10'),
('Bilecik', '11'),
('Bingöl', '12'),
('Bitlis', '13'),
('Bolu', '14'),
('Burdur', '15'),
('Bursa', '16'),
('Çanakkale', '17'),
('Çankırı', '18'),
('Çorum', '19'),
('Denizli', '20'),
('Diyarbakır', '21'),
('Edirne', '22'),
('Elazığ', '23'),
('Erzincan', '24'),
('Erzurum', '25'),
('Eskişehir', '26'),
('Gaziantep', '27'),
('Giresun', '28'),
('Gümüşhane', '29'),
('Hakkâri', '30'),
('Hatay', '31'),
('Isparta', '32'),
('Mersin', '33'),
('İstanbul', '34'),
('İzmir', '35'),
('Kars', '36'),
('Kastamonu', '37'),
('Kayseri', '38'),
('Kırklareli', '39'),
('Kırşehir', '40'),
('Kocaeli', '41'),
('Konya', '42'),
('Kütahya', '43'),
('Malatya', '44'),
('Manisa', '45'),
('Kahramanmaraş', '46'),
('Mardin', '47'),
('Muğla', '48'),
('Muş', '49'),
('Nevşehir', '50'),
('Niğde', '51'),
('Ordu', '52'),
('Rize', '53'),
('Sakarya', '54'),
('Samsun', '55'),
('Siirt', '56'),
('Sinop', '57'),
('Sivas', '58'),
('Tekirdağ', '59'),
('Tokat', '60'),
('Trabzon', '61'),
('Tunceli', '62'),
('Şanlıurfa', '63'),
('Uşak', '64'),
('Van', '65'),
('Yozgat', '66'),
('Zonguldak', '67'),
('Aksaray', '68'),
('Bayburt', '69'),
('Karaman', '70'),
('Kırıkkale', '71'),
('Batman', '72'),
('Şırnak', '73'),
('Bartın', '74'),
('Ardahan', '75'),
('Iğdır', '76'),
('Yalova', '77'),
('Karabük', '78'),
('Kilis', '79'),
('Osmaniye', '80'),
('Düzce', '81')
ON CONFLICT (province_code) DO NOTHING;