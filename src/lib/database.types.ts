export interface Database {
  public: {
    Tables: {
      companies: {
        Row: {
          id: string
          company_name: string
          phone: string
          authorized_person: string
          tax_number: string
          address: string
          logo: string | null
          email: string
          status: 'pending' | 'approved' | 'rejected'
          created_at: string
          updated_at: string
        }
        Insert: {
          id?: string
          company_name: string
          phone: string
          authorized_person: string
          tax_number: string
          address: string
          logo?: string | null
          email: string
          status?: 'pending' | 'approved' | 'rejected'
          created_at?: string
          updated_at?: string
        }
        Update: {
          id?: string
          company_name?: string
          phone?: string
          authorized_person?: string
          tax_number?: string
          address?: string
          logo?: string | null
          email?: string
          status?: 'pending' | 'approved' | 'rejected'
          created_at?: string
          updated_at?: string
        }
      }
      provinces: {
        Row: {
          id: string
          province_name: string
          province_code: string
          created_at: string
        }
        Insert: {
          id?: string
          province_name: string
          province_code: string
          created_at?: string
        }
        Update: {
          id?: string
          province_name?: string
          province_code?: string
          created_at?: string
        }
      }
      locations: {
        Row: {
          id: string
          company_id: string
          province_id: string | null
          location_name: string
          address: string | null
          created_at: string
        }
        Insert: {
          id?: string
          company_id: string
          province_id?: string | null
          location_name: string
          address?: string | null
          created_at?: string
        }
        Update: {
          id?: string
          company_id?: string
          province_id?: string | null
          location_name?: string
          address?: string | null
          created_at?: string
        }
      }
      users: {
        Row: {
          id: string
          company_id: string
          location_id: string | null
          province_id: string | null
          first_name: string
          last_name: string
          email: string
          role: 'admin' | 'hr' | 'employee'
          title: string | null
          department: string | null
          manager_id: string | null
          status: 'active' | 'inactive'
          last_login: string | null
          created_at: string
          updated_at: string
        }
        Insert: {
          id?: string
          company_id: string
          location_id?: string | null
          province_id?: string | null
          first_name: string
          last_name: string
          email: string
          role: 'admin' | 'hr' | 'employee'
          title?: string | null
          department?: string | null
          manager_id?: string | null
          status?: 'active' | 'inactive'
          last_login?: string | null
          created_at?: string
          updated_at?: string
        }
        Update: {
          id?: string
          company_id?: string
          location_id?: string | null
          province_id?: string | null
          first_name?: string
          last_name?: string
          email?: string
          role?: 'admin' | 'hr' | 'employee'
          title?: string | null
          department?: string | null
          manager_id?: string | null
          status?: 'active' | 'inactive'
          last_login?: string | null
          created_at?: string
          updated_at?: string
        }
      }
      request_categories: {
        Row: {
          id: string
          category_name: string
          requires_manager_approval: boolean
          created_at: string
        }
        Insert: {
          id?: string
          category_name: string
          requires_manager_approval?: boolean
          created_at?: string
        }
        Update: {
          id?: string
          category_name?: string
          requires_manager_approval?: boolean
          created_at?: string
        }
      }
      requests: {
        Row: {
          id: string
          request_number: string
          employee_id: string
          category_id: string
          title: string
          description: string
          attachment: string | null
          status: 'pending' | 'assigned' | 'in_progress' | 'manager_approval' | 'approved' | 'rejected' | 'completed' | 'cancelled'
          assigned_to: string | null
          priority: 'low' | 'medium' | 'high'
          created_at: string
          updated_at: string
          completed_at: string | null
        }
        Insert: {
          id?: string
          request_number: string
          employee_id: string
          category_id: string
          title: string
          description: string
          attachment?: string | null
          status?: 'pending' | 'assigned' | 'in_progress' | 'manager_approval' | 'approved' | 'rejected' | 'completed' | 'cancelled'
          assigned_to?: string | null
          priority?: 'low' | 'medium' | 'high'
          created_at?: string
          updated_at?: string
          completed_at?: string | null
        }
        Update: {
          id?: string
          request_number?: string
          employee_id?: string
          category_id?: string
          title?: string
          description?: string
          attachment?: string | null
          status?: 'pending' | 'assigned' | 'in_progress' | 'manager_approval' | 'approved' | 'rejected' | 'completed' | 'cancelled'
          assigned_to?: string | null
          priority?: 'low' | 'medium' | 'high'
          created_at?: string
          updated_at?: string
          completed_at?: string | null
        }
      }
      request_status_history: {
        Row: {
          id: string
          request_id: string
          old_status: string | null
          new_status: string
          changed_by: string
          notes: string | null
          created_at: string
        }
        Insert: {
          id?: string
          request_id: string
          old_status?: string | null
          new_status: string
          changed_by: string
          notes?: string | null
          created_at?: string
        }
        Update: {
          id?: string
          request_id?: string
          old_status?: string | null
          new_status?: string
          changed_by?: string
          notes?: string | null
          created_at?: string
        }
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      [_ in never]: never
    }
    Enums: {
      [_ in never]: never
    }
  }
}