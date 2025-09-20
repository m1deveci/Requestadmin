import { useEffect, useState } from 'react'
import { Building, Users, ClipboardList, Clock, MapPin, Settings } from 'lucide-react'
import { DashboardLayout } from '../../components/Layout/DashboardLayout'
import { StatCard } from '../../components/UI/StatCard'
import { Link } from 'react-router-dom'
import { apiClient } from '../../lib/api'

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
      // Load statistics from API
      const [companies, users, requests] = await Promise.all([
        apiClient.getCompanies(),
        apiClient.getUsers(),
        apiClient.getRequests()
      ]) as [any[], any[], any[]]

      setStats({
        totalCompanies: companies.length,
        pendingCompanies: companies.filter((c: any) => c.status === 'pending').length,
        totalUsers: users.length,
        totalRequests: requests.length,
        pendingRequests: requests.filter((r: any) => r.status === 'pending').length,
      })

      // Recent activities (last 10 requests)
      setRecentActivities(
        requests
          .sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
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
        <div className="page-header">
          <h1 className="page-title">Admin Dashboard</h1>
          <p className="page-subtitle">Sistem genelindeki aktiviteleri yönetin ve izleyin</p>
        </div>

        {/* Statistics Cards */}
        <div className="stats-grid">
          <StatCard
            title="Toplam Firma"
            value={stats.totalCompanies}
            icon={Building}
            color="blue"
            change={{ value: 12, type: 'increase' }}
          />
          <StatCard
            title="Bekleyen Firma"
            value={stats.pendingCompanies}
            icon={Clock}
            color="orange"
            change={{ value: 5, type: 'increase' }}
          />
          <StatCard
            title="Toplam Kullanıcı"
            value={stats.totalUsers}
            icon={Users}
            color="green"
            change={{ value: 8, type: 'increase' }}
          />
          <StatCard
            title="Toplam Talep"
            value={stats.totalRequests}
            icon={ClipboardList}
            color="purple"
            change={{ value: 3, type: 'decrease' }}
          />
        </div>

        {/* Content Grid */}
        <div className="content-grid">
          {/* Recent Activities */}
          <div className="lg:col-span-2">
            <div className="modern-card p-6">
              <h2 className="text-xl font-bold text-slate-900 mb-6">Son Aktiviteler</h2>
              {recentActivities.length === 0 ? (
                <div className="text-center py-12">
                  <ClipboardList className="w-12 h-12 text-slate-300 mx-auto mb-4" />
                  <p className="text-slate-500 font-medium">Henüz aktivite bulunmuyor.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {recentActivities.map((activity, index) => (
                    <div key={index} className="flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 rounded-xl transition-colors duration-200">
                      <div className="flex items-center">
                        <div className="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                          <ClipboardList className="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                          <span className="font-semibold text-slate-900">{activity.title}</span>
                          <p className="text-sm text-slate-600">Talep oluşturuldu</p>
                        </div>
                      </div>
                      <span className="text-sm text-slate-500 font-medium">
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
            <div className="modern-card p-6">
              <h2 className="text-xl font-bold text-slate-900 mb-6">Hızlı İşlemler</h2>
              <div className="space-y-3">
                <Link
                  to="/admin/companies?status=pending"
                  className="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition-all duration-200 group hover:scale-[1.02]"
                >
                  <div className="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center mr-4">
                    <Building className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <span className="font-semibold text-slate-900">Firma Onayları</span>
                    <p className="text-sm text-slate-600">Bekleyen firma başvuruları</p>
                  </div>
                </Link>
                <Link
                  to="/admin/users?action=add"
                  className="flex items-center p-4 bg-emerald-50 hover:bg-emerald-100 rounded-xl transition-all duration-200 group hover:scale-[1.02]"
                >
                  <div className="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center mr-4">
                    <Users className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <span className="font-semibold text-slate-900">Kullanıcı Ekle</span>
                    <p className="text-sm text-slate-600">Yeni kullanıcı oluştur</p>
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