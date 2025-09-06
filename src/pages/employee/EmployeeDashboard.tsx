import { useEffect, useState } from 'react'
import { ClipboardList, Clock, Cog, CheckCircle, Plus } from 'lucide-react'
import { Link } from 'react-router-dom'
import { DashboardLayout } from '../../components/Layout/DashboardLayout'
import { StatCard } from '../../components/UI/StatCard'
import { StatusBadge } from '../../components/UI/StatusBadge'
import { supabase } from '../../lib/supabase'
import { useAuth } from '../../hooks/useAuth'

export function EmployeeDashboard() {
  const { user } = useAuth()
  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    inProgress: 0,
    completed: 0,
  })
  const [recentRequests, setRecentRequests] = useState<any[]>([])
  const [categories, setCategories] = useState<any[]>([])

  useEffect(() => {
    if (user) {
      loadDashboardData()
      loadCategories()
    }
  }, [user])

  const loadDashboardData = async () => {
    try {
      const { data: requests } = await supabase
        .from('requests')
        .select(`
          *,
          request_categories(category_name),
          assigned_user:users!requests_assigned_to_fkey(first_name, last_name)
        `)
        .eq('employee_id', user?.id)
        .order('created_at', { ascending: false })
        .limit(10)

      if (requests) {
        setStats({
          total: requests.length,
          pending: requests.filter(r => r.status === 'pending').length,
          inProgress: requests.filter(r => ['assigned', 'in_progress'].includes(r.status)).length,
          completed: requests.filter(r => r.status === 'completed').length,
        })
        setRecentRequests(requests)
      }
    } catch (error) {
      console.error('Error loading dashboard data:', error)
    }
  }

  const loadCategories = async () => {
    try {
      const { data } = await supabase
        .from('request_categories')
        .select('*')
        .order('category_name')

      if (data) {
        setCategories(data)
      }
    } catch (error) {
      console.error('Error loading categories:', error)
    }
  }

  return (
    <DashboardLayout>
      <div className="space-y-8">
        {/* Page Header */}
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Çalışan Dashboard</h1>
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
            title="Beklemede"
            value={stats.pending}
            icon={Clock}
            gradient="from-orange-500 to-red-600"
          />
          <StatCard
            title="İşlemde"
            value={stats.inProgress}
            icon={Cog}
            gradient="from-cyan-500 to-blue-600"
          />
          <StatCard
            title="Tamamlanan"
            value={stats.completed}
            icon={CheckCircle}
            gradient="from-green-500 to-teal-600"
          />
        </div>

        {/* Quick Actions */}
        <div className="bg-white rounded-xl shadow-lg p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-6">Hızlı Talep Oluştur</h2>
          <div className="flex items-center justify-between">
            <p className="text-gray-600">Yeni bir talep oluşturmak için kategorinizi seçin</p>
            <Link
              to="/employee/new-request"
              className="btn-primary flex items-center"
            >
              <Plus className="w-5 h-5 mr-2" />
              Yeni Talep Oluştur
            </Link>
          </div>
        </div>

        {/* Recent Requests */}
        <div className="bg-white rounded-xl shadow-lg p-6">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-xl font-semibold text-gray-900">Son Taleplerim</h2>
            <Link
              to="/employee/requests"
              className="text-blue-600 hover:text-blue-700 font-medium"
            >
              Tümünü Gör
            </Link>
          </div>

          {recentRequests.length === 0 ? (
            <div className="text-center py-12">
              <ClipboardList className="w-16 h-16 text-gray-300 mx-auto mb-4" />
              <p className="text-gray-500 mb-4">Henüz talep oluşturmamışsınız.</p>
              <Link
                to="/employee/new-request"
                className="btn-primary"
              >
                İlk Talebinizi Oluşturun
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-semibold text-gray-900">Talep No</th>
                    <th className="text-left py-3 px-4 font-semibold text-gray-900">Başlık</th>
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
                          to={`/employee/requests/${request.id}`}
                          className="text-blue-600 hover:text-blue-700 font-medium"
                        >
                          {request.request_number}
                        </Link>
                      </td>
                      <td className="py-3 px-4">{request.title}</td>
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
    </DashboardLayout>
  )
}