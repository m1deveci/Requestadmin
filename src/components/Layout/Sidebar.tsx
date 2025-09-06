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
  
  const userRole = user?.user_metadata?.role || 'employee'
  const userName = `${user?.user_metadata?.first_name || ''} ${user?.user_metadata?.last_name || ''}`.trim()
  
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
    <div className="w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white min-h-screen">
      <div className="p-6">
        <div className="text-center mb-8">
          <h2 className="text-xl font-bold">{getRoleTitle(userRole)}</h2>
          <p className="text-gray-300 text-sm mt-1">{userName || 'Kullanıcı'}</p>
        </div>
        
        <nav className="space-y-2">
          {navItems.map((item) => (
            <NavLink
              key={item.name}
              to={item.href}
              className={({ isActive }) =>
                `sidebar-link ${isActive ? 'active' : ''}`
              }
            >
              <item.icon className="w-5 h-5 mr-3" />
              {item.name}
            </NavLink>
          ))}
          
          <button
            onClick={handleSignOut}
            className="sidebar-link w-full text-left mt-8 text-red-300 hover:text-red-200"
          >
            <LogOut className="w-5 h-5 mr-3" />
            Çıkış
          </button>
        </nav>
      </div>
    </div>
  )
}