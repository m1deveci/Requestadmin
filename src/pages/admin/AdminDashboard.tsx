import { useEffect, useState } from 'react'
import { Building, Users, ClipboardList, Clock } from 'lucide-react'
import { DashboardLayout } from '../../components/Layout/DashboardLayout'
import { StatCard } from '../../components/UI/StatCard'
import { supabase } from '../../lib/supabase'

interface DashboardStats {
  totalCompanies: number
  pendingCompanies: number
  totalUsers: number
  totalRequests: number
  pendingRequests: number
}

export function AdminDashboard() {
  const [stats, setStats] = useState<DashboardStats>({
    totalCompanies: 0,
    pendingCompanies: 0,
    totalUsers: 0,
    totalRequests: 0,
    pendingRequests: 0,
  })
  const [recentActivities, setRecentActivities] = useState<any[]>([])

  useEffect(() => {
    loadDashboardData()
  }, [])

  const loadDashboardData = async () => {
    try {
      // Load statistics
      const [companiesResult, usersResult, requestsResult] = await Promise.all([
        supabase.from('companies').select('id, status'),
        supabase.from('users').select('id'),
        supabase.from('requests').select('id, status, created_at, title')
      ])

      const companies = companiesResult.data || []
      const users = usersResult.data || []
      const requests = requestsResult.data || []

      setStats({
        totalCompanies: companies.length,
        pendingCompanies: companies.filter(c => c.status === 'pending').length,
        totalUsers: users.length,
        totalRequests: requests.length,
        pendingRequests: requests.filter(r => r.status === 'pending').length,
      })

      // Recent activities (last 10 requests)
      setRecentActivities(
        requests
          .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
          .slice(0, 10)
      )
    } catch (error) {
      console.error('Error loading dashboard data:', error)
    }
  }

  return (
    <DashboardLayout>
      <div className="space-y-8">
        {/* Page Header */}
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
          <nav className="flex mt-2" aria-label="Breadcrumb">
            <ol className="flex items-center space-x-2">
              <li className="text-gray-500">Dashboard</li>
            </ol>
          </nav>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatCard
            title="Toplam Firma"
            value={stats.totalCompanies}
            icon={Building}
          />
          <StatCard
            title="Bekleyen Firma"
            value={stats.pendingCompanies}
            icon={Clock}
            gradient="from-orange-500 to-red-600"
          />
          <StatCard
            title="Toplam Kullanıcı"
            value={stats.totalUsers}
            icon={Users}
            gradient="from-cyan-500 to-blue-600"
          />
          <StatCard
            title="Toplam Talep"
            value={stats.totalRequests}
            icon={ClipboardList}
            gradient="from-green-500 to-teal-600"
          />
        </div>

        {/* Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Recent Activities */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-xl shadow-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-6">Son Aktiviteler</h2>
              {recentActivities.length === 0 ? (
                <p className="text-gray-500 text-center py-8">Henüz aktivite bulunmuyor.</p>
              ) : (
                <div className="space-y-4">
                  {recentActivities.map((activity, index) => (
                    <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                      <div className="flex items-center">
                        <ClipboardList className="w-5 h-5 text-blue-600 mr-3" />
                        <span className="text-gray-900">{activity.title}</span>
                      </div>
                      <span className="text-sm text-gray-500">
                        {new Date(activity.created_at).toLocaleDateString('tr-TR')}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Quick Actions */}
          <div>
            <div className="bg-white rounded-xl shadow-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-6">Hızlı İşlemler</h2>
              <div className="space-y-3">
                <Link
                  to="/admin/companies?status=pending"
                  className="block w-full text-left p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <Building className="w-5 h-5 text-blue-600 mr-3" />
                    <span className="text-gray-900">Firma Onayları</span>
                  </div>
                </Link>
                <Link
                  to="/admin/users?action=add"
                  className="block w-full text-left p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <Users className="w-5 h-5 text-green-600 mr-3" />
                    <span className="text-gray-900">Kullanıcı Ekle</span>
                  </div>
                </Link>
                <Link
                  to="/admin/locations?action=add"
                  className="block w-full text-left p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <MapPin className="w-5 h-5 text-purple-600 mr-3" />
                    <span className="text-gray-900">Lokasyon Ekle</span>
                  </div>
                </Link>
                <Link
                  to="/admin/settings"
                  className="block w-full text-left p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <Settings className="w-5 h-5 text-gray-600 mr-3" />
                    <span className="text-gray-900">Sistem Ayarları</span>
                  </div>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  )
}