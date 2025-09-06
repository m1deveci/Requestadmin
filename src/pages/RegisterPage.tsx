import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Building, CheckCircle } from 'lucide-react'
import { CompanyRegisterForm } from '../components/Auth/CompanyRegisterForm'

export function RegisterPage() {
  const [isSuccess, setIsSuccess] = useState(false)

  if (isSuccess) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg p-8 w-full max-w-md text-center">
          <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <CheckCircle className="w-8 h-8 text-green-600" />
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-4">Kayıt Başarılı!</h2>
          <p className="text-gray-600 mb-6">
            Firma kaydınız başarıyla oluşturuldu. Onay sürecinden sonra sistemi kullanmaya başlayabilirsiniz.
          </p>
          <Link
            to="/"
            className="btn-primary inline-block"
          >
            Ana Sayfaya Dön
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Navigation */}
      <nav className="bg-blue-600 text-white shadow-lg">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <Link to="/" className="flex items-center">
              <Building className="w-8 h-8 mr-3" />
              <span className="text-xl font-bold">Request Admin</span>
            </Link>
            <Link to="/" className="hover:text-blue-200 transition-colors">
              Ana Sayfa
            </Link>
          </div>
        </div>
      </nav>

      {/* Registration Form */}
      <div className="py-12">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white rounded-xl shadow-lg overflow-hidden">
            <div className="bg-blue-600 text-white p-6">
              <h1 className="text-2xl font-bold">Firma Kayıt Formu</h1>
              <p className="text-blue-100 mt-2">Sistemi kullanmaya başlamak için firma bilgilerinizi girin</p>
            </div>
            <div className="p-8">
              <CompanyRegisterForm onSuccess={() => setIsSuccess(true)} />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}