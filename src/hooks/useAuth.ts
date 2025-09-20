import { useEffect, useState } from 'react'
import { apiClient } from '../lib/api'

export interface AuthUser {
  id: string
  email: string
  firstName: string
  lastName: string
  role: 'admin' | 'hr' | 'employee'
  companyId: string
  companyName?: string
  title?: string
  department?: string
  status: 'active' | 'inactive'
}

export function useAuth() {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Check if user is already logged in
    const token = localStorage.getItem('token')
    if (token) {
      // Verify token and get user info
      apiClient.getMe()
        .then((userData: unknown) => {
          setUser(userData as AuthUser)
          setLoading(false)
        })
        .catch(() => {
          // Token is invalid, clear it
          apiClient.clearToken()
          setUser(null)
          setLoading(false)
        })
    } else {
      setLoading(false)
    }
  }, [])

  const signIn = async (email: string, password: string) => {
    try {
      const response: any = await apiClient.login(email, password)
      apiClient.setToken(response.token)
      setUser(response.user)
      return { data: response, error: null }
    } catch (error) {
      return { data: null, error: error as Error }
    }
  }

  const signUp = async (data: {
    companyName: string
    email: string
    phone: string
    authorizedPerson: string
    taxNumber: string
    address: string
    firstName: string
    lastName: string
    password: string
  }) => {
    try {
      const response = await apiClient.register(data)
      return { data: response, error: null }
    } catch (error) {
      return { data: null, error: error as Error }
    }
  }

  const signOut = async () => {
    try {
      await apiClient.logout()
      apiClient.clearToken()
      setUser(null)
      return { error: null }
    } catch (error) {
      // Even if logout fails on server, clear local state
      apiClient.clearToken()
      setUser(null)
      return { error: error as Error }
    }
  }

  return {
    user,
    loading,
    signIn,
    signUp,
    signOut,
  }
}