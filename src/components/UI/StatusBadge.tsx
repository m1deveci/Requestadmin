interface StatusBadgeProps {
  status: string
  type?: 'status' | 'priority' | 'role'
}

export function StatusBadge({ status, type = 'status' }: StatusBadgeProps) {
  const getStatusStyles = (status: string) => {
    switch (status) {
      case 'pending':
        return 'bg-yellow-100 text-yellow-800'
      case 'assigned':
        return 'bg-blue-100 text-blue-800'
      case 'in_progress':
        return 'bg-purple-100 text-purple-800'
      case 'completed':
        return 'bg-green-100 text-green-800'
      case 'cancelled':
        return 'bg-red-100 text-red-800'
      case 'rejected':
        return 'bg-red-100 text-red-800'
      case 'manager_approval':
        return 'bg-gray-100 text-gray-800'
      case 'approved':
        return 'bg-green-100 text-green-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  const getPriorityStyles = (priority: string) => {
    switch (priority) {
      case 'low':
        return 'bg-green-100 text-green-800'
      case 'medium':
        return 'bg-yellow-100 text-yellow-800'
      case 'high':
        return 'bg-red-100 text-red-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  const getRoleStyles = (role: string) => {
    switch (role) {
      case 'admin':
        return 'bg-red-100 text-red-800'
      case 'hr':
        return 'bg-yellow-100 text-yellow-800'
      case 'employee':
        return 'bg-blue-100 text-blue-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  const getStatusText = (status: string) => {
    switch (status) {
      case 'pending': return 'Beklemede'
      case 'assigned': return 'Atanmış'
      case 'in_progress': return 'İşlemde'
      case 'completed': return 'Tamamlandı'
      case 'cancelled': return 'İptal Edildi'
      case 'rejected': return 'Reddedildi'
      case 'manager_approval': return 'Yönetici Onayı'
      case 'approved': return 'Onaylandı'
      case 'low': return 'Düşük'
      case 'medium': return 'Orta'
      case 'high': return 'Yüksek'
      case 'admin': return 'Admin'
      case 'hr': return 'İdari İşler'
      case 'employee': return 'Çalışan'
      case 'active': return 'Aktif'
      case 'inactive': return 'Pasif'
      default: return status
    }
  }

  let styles = ''
  if (type === 'priority') {
    styles = getPriorityStyles(status)
  } else if (type === 'role') {
    styles = getRoleStyles(status)
  } else {
    styles = getStatusStyles(status)
  }

  return (
    <span className={`status-badge ${styles}`}>
      {getStatusText(status)}
    </span>
  )
}