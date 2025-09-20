interface StatusBadgeProps {
  status: string
  type?: 'status' | 'priority' | 'role'
}

export function StatusBadge({ status, type = 'status' }: StatusBadgeProps) {
  const getStatusStyles = (status: string) => {
    switch (status) {
      case 'pending':
        return 'status-pending'
      case 'assigned':
        return 'status-in-progress'
      case 'in_progress':
        return 'status-in-progress'
      case 'completed':
        return 'status-approved'
      case 'cancelled':
        return 'status-rejected'
      case 'rejected':
        return 'status-rejected'
      case 'manager_approval':
        return 'status-pending'
      case 'approved':
        return 'status-approved'
      default:
        return 'bg-slate-100 text-slate-600'
    }
  }

  const getPriorityStyles = (priority: string) => {
    switch (priority) {
      case 'low':
        return 'priority-low'
      case 'medium':
        return 'priority-medium'
      case 'high':
        return 'priority-high'
      default:
        return 'bg-slate-100 text-slate-600'
    }
  }

  const getRoleStyles = (role: string) => {
    switch (role) {
      case 'admin':
        return 'bg-red-50 text-red-700 border border-red-200'
      case 'hr':
        return 'bg-amber-50 text-amber-700 border border-amber-200'
      case 'employee':
        return 'bg-blue-50 text-blue-700 border border-blue-200'
      case 'active':
        return 'status-approved'
      case 'inactive':
        return 'status-rejected'
      default:
        return 'bg-slate-100 text-slate-600'
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
    <span className={`status-badge ${styles} scale-in`}>
      {getStatusText(status)}
    </span>
  )
}