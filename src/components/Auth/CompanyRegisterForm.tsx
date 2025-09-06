import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { supabase } from '../../lib/supabase'
import { Building, Upload } from 'lucide-react'

const registerSchema = z.object({
  company_name: z.string().min(1, 'Firma adı gereklidir'),
  phone: z.string().min(1, 'Telefon gereklidir'),
  authorized_person: z.string().min(1, 'Yetkili kişi gereklidir'),
  tax_number: z.string().min(1, 'Vergi numarası gereklidir'),
  address: z.string().min(1, 'Adres gereklidir'),
  email: z.string().email('Geçerli bir e-posta adresi girin'),
  password: z.string().min(6, 'Parola en az 6 karakter olmalıdır'),
  confirmPassword: z.string(),
}).refine((data) => data.password === data.confirmPassword, {
  message: "Parolalar eşleşmiyor",
  path: ["confirmPassword"],
})

type RegisterFormData = z.infer<typeof registerSchema>

interface CompanyRegisterFormProps {
  onSuccess?: () => void
}

export function CompanyRegisterForm({ onSuccess }: CompanyRegisterFormProps) {
  const [isLoading, setIsLoading] = useState(false)
  const [logoFile, setLogoFile] = useState<File | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors },
    setError,
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
  })

  const onSubmit = async (data: RegisterFormData) => {
    setIsLoading(true)
    try {
      // Upload logo if provided
      let logoUrl = null
      if (logoFile) {
        const fileExt = logoFile.name.split('.').pop()
        const fileName = `${Math.random()}.${fileExt}`
        const { error: uploadError } = await supabase.storage
          .from('company-logos')
          .upload(fileName, logoFile)
        
        if (!uploadError) {
          logoUrl = fileName
        }
      }

      // Insert company
      const { error } = await supabase
        .from('companies')
        .insert({
          company_name: data.company_name,
          phone: data.phone,
          authorized_person: data.authorized_person,
          tax_number: data.tax_number,
          address: data.address,
          email: data.email,
          logo: logoUrl,
          status: 'pending'
        })

      if (error) {
        if (error.code === '23505') {
          setError('email', { message: 'Bu e-posta adresi zaten kullanılıyor' })
        } else {
          setError('root', { message: 'Kayıt sırasında bir hata oluştu' })
        }
      } else {
        onSuccess?.()
      }
    } catch (error) {
      setError('root', { message: 'Kayıt sırasında bir hata oluştu' })
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Firma Adı *
          </label>
          <input
            {...register('company_name')}
            className="form-input"
            placeholder="Firma adınızı girin"
          />
          {errors.company_name && (
            <p className="mt-1 text-sm text-red-600">{errors.company_name.message}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Telefon *
          </label>
          <input
            {...register('phone')}
            type="tel"
            className="form-input"
            placeholder="+90 212 555 0123"
          />
          {errors.phone && (
            <p className="mt-1 text-sm text-red-600">{errors.phone.message}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Yetkili Kişi *
          </label>
          <input
            {...register('authorized_person')}
            className="form-input"
            placeholder="Yetkili kişi adı"
          />
          {errors.authorized_person && (
            <p className="mt-1 text-sm text-red-600">{errors.authorized_person.message}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Vergi Numarası *
          </label>
          <input
            {...register('tax_number')}
            className="form-input"
            placeholder="1234567890"
          />
          {errors.tax_number && (
            <p className="mt-1 text-sm text-red-600">{errors.tax_number.message}</p>
          )}
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Adres *
        </label>
        <textarea
          {...register('address')}
          rows={3}
          className="form-input"
          placeholder="Firma adresinizi girin"
        />
        {errors.address && (
          <p className="mt-1 text-sm text-red-600">{errors.address.message}</p>
        )}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Firma Logosu
        </label>
        <div className="flex items-center space-x-4">
          <input
            type="file"
            accept="image/*"
            onChange={(e) => setLogoFile(e.target.files?.[0] || null)}
            className="hidden"
            id="logo-upload"
          />
          <label
            htmlFor="logo-upload"
            className="flex items-center px-4 py-2 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50"
          >
            <Upload className="w-5 h-5 mr-2" />
            Logo Seç
          </label>
          {logoFile && (
            <span className="text-sm text-gray-600">{logoFile.name}</span>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            E-posta *
          </label>
          <input
            {...register('email')}
            type="email"
            className="form-input"
            placeholder="firma@example.com"
          />
          {errors.email && (
            <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Parola *
          </label>
          <input
            {...register('password')}
            type="password"
            className="form-input"
            placeholder="En az 6 karakter"
          />
          {errors.password && (
            <p className="mt-1 text-sm text-red-600">{errors.password.message}</p>
          )}
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Parola Tekrar *
        </label>
        <input
          {...register('confirmPassword')}
          type="password"
          className="form-input"
          placeholder="Parolanızı tekrar girin"
        />
        {errors.confirmPassword && (
          <p className="mt-1 text-sm text-red-600">{errors.confirmPassword.message}</p>
        )}
      </div>

      {errors.root && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-3">
          <p className="text-sm text-red-600">{errors.root.message}</p>
        </div>
      )}

      <button
        type="submit"
        disabled={isLoading}
        className="w-full btn-primary flex items-center justify-center"
      >
        {isLoading ? (
          <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
        ) : (
          <>
            <Building className="w-5 h-5 mr-2" />
            Firma Kaydı Oluştur
          </>
        )}
      </button>
    </form>
  )
}