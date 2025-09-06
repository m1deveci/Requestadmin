import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Building, Users, MapPin, ClipboardList, Mail, Phone } from 'lucide-react'
import { LoginForm } from '../components/Auth/LoginForm'

export function HomePage() {
  const [showLoginModal, setShowLoginModal] = useState(false)

  const features = [
    {
      icon: Users,
      title: 'Çoklu Kullanıcı Yönetimi',
      description: 'Admin, İdari İşler ve Çalışan rolleri ile kapsamlı yetkilendirme'
    },
    {
      icon: MapPin,
      title: 'Lokasyon Bazlı Yönetim',
      description: 'Farklı lokasyonlar için ayrı yetkilendirme ve talep yönetimi'
    },
    {
      icon: ClipboardList,
      title: 'Talep Takip Sistemi',
      description: 'Taleplerinizi kategorize edin, takip edin ve raporlayın'
    }
  ]

  return (
    <div className="min-h-screen bg-white">
      {/* Navigation */}
      <nav className="bg-blue-600 text-white shadow-lg">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center">
              <Building className="w-8 h-8 mr-3" />
              <span className="text-xl font-bold">Request Admin</span>
            </div>
            <div className="flex items-center space-x-4">
              <Link to="/register" className="hover:text-blue-200 transition-colors">
                Firma Kayıt
              </Link>
              <button
                onClick={() => setShowLoginModal(true)}
                className="bg-blue-700 hover:bg-blue-800 px-4 py-2 rounded-lg transition-colors"
              >
                Giriş Yap
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="bg-gradient-to-br from-blue-600 to-purple-700 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
              <h1 className="text-5xl font-bold mb-6 leading-tight">
                İdari İşler Talep Yönetim Sistemi
              </h1>
              <p className="text-xl mb-8 text-blue-100">
                Firmanızın idari işler süreçlerini dijitalleştirin. Talepleri kolayca yönetin, takip edin ve raporlayın.
              </p>
              <div className="flex flex-col sm:flex-row gap-4">
                <Link
                  to="/register"
                  className="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors text-center"
                >
                  Firma Kaydı
                </Link>
                <button
                  onClick={() => setShowLoginModal(true)}
                  className="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors"
                >
                  Giriş Yap
                </button>
              </div>
            </div>
            <div className="hidden lg:block">
              <img
                src="https://images.pexels.com/photos/3184465/pexels-photo-3184465.jpeg?auto=compress&cs=tinysrgb&w=800"
                alt="Request Management"
                className="rounded-xl shadow-2xl"
              />
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Sistem Özellikleri</h2>
            <p className="text-xl text-gray-600">Modern ve kullanıcı dostu arayüz ile idari işlerinizi kolaylaştırın</p>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {features.map((feature, index) => (
              <div key={index} className="bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-6 mx-auto">
                  <feature.icon className="w-8 h-8 text-blue-600" />
                </div>
                <h3 className="text-xl font-semibold text-gray-900 mb-4 text-center">{feature.title}</h3>
                <p className="text-gray-600 text-center">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Contact Section */}
      <section className="bg-gray-50 py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">İletişim</h2>
            <p className="text-xl text-gray-600 mb-12">Sorularınız için bizimle iletişime geçin</p>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-2xl mx-auto">
              <div className="text-center">
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4 mx-auto">
                  <Mail className="w-8 h-8 text-blue-600" />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 mb-2">E-posta</h3>
                <p className="text-gray-600">info@requestadmin.com</p>
              </div>
              
              <div className="text-center">
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4 mx-auto">
                  <Phone className="w-8 h-8 text-blue-600" />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Telefon</h3>
                <p className="text-gray-600">+90 (212) 555 0123</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Login Modal */}
      {showLoginModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl p-8 w-full max-w-md">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-2xl font-bold text-gray-900">Giriş Yap</h2>
              <button
                onClick={() => setShowLoginModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                ✕
              </button>
            </div>
            <LoginForm onSuccess={() => setShowLoginModal(false)} />
          </div>
        </div>
      )}
    </div>
  )
}