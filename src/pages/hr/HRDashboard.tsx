import { useEffect, useState } from 'react'
import { ClipboardList, Clock, UserCheck, CheckCircle, Plus } from 'lucide-react'
import { Link } from 'react-router-dom'
import { DashboardLayout } from '../../components/Layout/DashboardLayout'
import { StatCard } from '../../components/UI/StatCard'
import { StatusBadge } from '../../components/UI/StatusBadge'
import { apiClient } from '../../lib/api'
import { useAuth } from '../../hooks/useAuth'

export function HRDashboard() {
  const { user } = useAuth()
  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    assigned: 0,
    completed: 0,
  })
  const [recentRequests, setRecentRequests] = useState<any[]>([])

  useEffect(() => {
    if (user) {
      loadDashboardData()
    }
  }, [user])

  const loadDashboardData = async () => {
    try {
      const provinceId = user?.id

      const requests = await apiClient.getRequests() as any[]

      if (requests) {
        const filteredRequests = requests.filter((r: any) => r.employee?.province_id === provinceId)
        
        setStats({
          total: filteredRequests.length,
          pending: filteredRequests.filter((r: any) => r.status === 'pending').length,
          assigned: filteredRequests.filter((r: any) => r.assigned_to === user?.id).length,
          completed: filteredRequests.filter((r: any) => r.status === 'completed').length,
        })
        setRecentRequests(filteredRequests)
      }
    } catch (error) {
      console.error('Error loading dashboard data:', error)
    }
  }

  return (
    <DashboardLayout>
      <div className="space-y-8">
        {/* Page Header */}
        <div>
          <h1 className="text-3xl font-bold text-gray-900">İdari İşler Dashboard</h1>
          <nav className="flex mt-2" aria-label="Breadcrumb">
            <ol className="flex items-center space-x-2">
              <li className="text-gray-500">Dashboard</li>
            </ol>
          </nav>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatCard
            title="Toplam Talep"
            value={stats.total}
            icon={ClipboardList}
          />
          <StatCard
            title="Bekleyen Talep"
            value={stats.pending}
            icon={Clock}
            color="orange"
          />
          <StatCard
            title="Bana Atanan"
            value={stats.assigned}
            icon={UserCheck}
            color="blue"
          />
          <StatCard
            title="Tamamlanan"
            value={stats.completed}
            icon={CheckCircle}
            color="green"
          />
        </div>

        {/* Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Recent Requests */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-xl shadow-lg p-6">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-semibold text-gray-900">Son Talepler</h2>
                <Link
                  to="/hr/requests"
                  className="text-blue-600 hover:text-blue-700 font-medium"
                >
                  Tümünü Gör
                </Link>
              </div>

              {recentRequests.length === 0 ? (
                <p className="text-gray-500 text-center py-8">Henüz talep bulunmuyor.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-gray-200">
                        <th className="text-left py-3 px-4 font-semibold text-gray-900">Talep No</th>
                        <th className="text-left py-3 px-4 font-semibold text-gray-900">Çalışan</th>
                        <th className="text-left py-3 px-4 font-semibold text-gray-900">Kategori</th>
                        <th className="text-left py-3 px-4 font-semibold text-gray-900">Durum</th>
                        <th className="text-left py-3 px-4 font-semibold text-gray-900">Tarih</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recentRequests.map((request) => (
                        <tr key={request.id} className="border-b border-gray-100 hover:bg-gray-50">
                          <td className="py-3 px-4">
                            <Link
                              to={`/hr/requests/${request.id}`}
                              className="text-blue-600 hover:text-blue-700 font-medium"
                            >
                              {request.request_number}
                            </Link>
                          </td>
                          <td className="py-3 px-4">
                            {request.employee?.first_name} {request.employee?.last_name}
                          </td>
                          <td className="py-3 px-4">{request.request_categories?.category_name}</td>
                          <td className="py-3 px-4">
                            <StatusBadge status={request.status} />
                          </td>
                          <td className="py-3 px-4 text-gray-500">
                            {new Date(request.created_at).toLocaleDateString('tr-TR')}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
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
                  to="/hr/requests?status=pending"
                  className="block w-full text-left p-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <Clock className="w-5 h-5 text-yellow-600 mr-3" />
                    <span className="text-gray-900">Bekleyen Talepler</span>
                  </div>
                </Link>
                <Link
                  to="/hr/requests?assigned_to=me"
                  className="block w-full text-left p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <UserCheck className="w-5 h-5 text-blue-600 mr-3" />
                    <span className="text-gray-900">Bana Atananlar</span>
                  </div>
                </Link>
                <Link
                  to="/hr/employees?action=add"
                  className="block w-full text-left p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <Plus className="w-5 h-5 text-green-600 mr-3" />
                    <span className="text-gray-900">Çalışan Ekle</span>
                  </div>
                </Link>
                <Link
                  to="/hr/reports"
                  className="block w-full text-left p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors"
                >
                  <div className="flex items-center">
                    <ClipboardList className="w-5 h-5 text-purple-600 mr-3" />
                    <span className="text-gray-900">Raporlar</span>
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