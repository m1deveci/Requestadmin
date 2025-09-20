import { NavLink, useNavigate } from 'react-router-dom'
import { 
  LayoutDashboard, 
  Building, 
  MapPin, 
  Users, 
  ClipboardList, 
  Tags, 
  Settings, 
  FileText, 
  LogOut,
  Plus,
  BarChart3,
  User
} from 'lucide-react'
import { useAuth } from '../../hooks/useAuth'

const navigation = {
  admin: [
    { name: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { name: 'Firmalar', href: '/admin/companies', icon: Building },
    { name: 'Lokasyonlar', href: '/admin/locations', icon: MapPin },
    { name: 'Kullanıcılar', href: '/admin/users', icon: Users },
    { name: 'Talepler', href: '/admin/requests', icon: ClipboardList },
    { name: 'Kategoriler', href: '/admin/categories', icon: Tags },
    { name: 'Ayarlar', href: '/admin/settings', icon: Settings },
    { name: 'Loglar', href: '/admin/logs', icon: FileText },
  ],
  hr: [
    { name: 'Dashboard', href: '/hr', icon: LayoutDashboard },
    { name: 'Talepler', href: '/hr/requests', icon: ClipboardList },
    { name: 'Çalışanlar', href: '/hr/employees', icon: Users },
    { name: 'Raporlar', href: '/hr/reports', icon: BarChart3 },
    { name: 'Profil', href: '/hr/profile', icon: User },
  ],
  employee: [
    { name: 'Dashboard', href: '/employee', icon: LayoutDashboard },
    { name: 'Taleplerim', href: '/employee/requests', icon: ClipboardList },
    { name: 'Yeni Talep', href: '/employee/new-request', icon: Plus },
    { name: 'Profil', href: '/employee/profile', icon: User },
  ]
}

export function Sidebar() {
  const { user, signOut } = useAuth()
  const navigate = useNavigate()
  
  const userRole = user?.role || 'employee'
  const userName = `${user?.firstName || ''} ${user?.lastName || ''}`.trim()
  
  const navItems = navigation[userRole as keyof typeof navigation] || navigation.employee

  const handleSignOut = async () => {
    await signOut()
    navigate('/')
  }

  const getRoleTitle = (role: string) => {
    switch (role) {
      case 'admin': return 'Admin Panel'
      case 'hr': return 'İdari İşler'
      case 'employee': return 'Çalışan Paneli'
      default: return 'Panel'
    }
  }

  return (
    <div className="w-72 sidebar min-h-screen">
      {/* Header */}
      <div className="sidebar-header p-6">
        <div className="text-center">
          <div className="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-4 mx-auto">
            <LayoutDashboard className="w-8 h-8" />
          </div>
          <h2 className="text-xl font-bold">{getRoleTitle(userRole)}</h2>
          <p className="text-blue-100 text-sm mt-1 font-medium">{userName || 'Kullanıcı'}</p>
        </div>
      </div>

      {/* Navigation */}
      <div className="p-4">
        <nav className="space-y-1">
          {navItems.map((item) => (
            <NavLink
              key={item.name}
              to={item.href}
              className={({ isActive }) =>
                `sidebar-link ${isActive ? 'active' : ''}`
              }
            >
              <div className="flex items-center">
                <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mr-3 group-hover:bg-slate-200 transition-colors">
                  <item.icon className="w-5 h-5 text-slate-600" />
                </div>
                <span className="font-medium">{item.name}</span>
              </div>
            </NavLink>
          ))}
        </nav>

        {/* Logout Button */}
        <div className="mt-8 pt-4 border-t border-slate-200">
          <button
            onClick={handleSignOut}
            className="w-full flex items-center px-4 py-3 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-xl transition-all duration-200 font-medium"
          >
            <div className="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center mr-3">
              <LogOut className="w-5 h-5" />
            </div>
            <span>Çıkış Yap</span>
          </button>
        </div>
      </div>
    </div>
  )
}