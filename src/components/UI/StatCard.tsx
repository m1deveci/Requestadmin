import { LucideIcon } from 'lucide-react'

interface StatCardProps {
  title: string
  value: number | string
  icon: LucideIcon
  change?: {
    value: number
    type: 'increase' | 'decrease'
  }
  color?: 'blue' | 'green' | 'purple' | 'orange' | 'red'
}

export function StatCard({ title, value, icon: Icon, change, color = 'blue' }: StatCardProps) {
  const colorConfig = {
    blue: {
      gradient: 'gradient-bg-3',
      iconBg: 'bg-blue-500/10',
      iconColor: 'text-blue-600',
      textColor: 'text-slate-600'
    },
    green: {
      gradient: 'gradient-bg-4',
      iconBg: 'bg-emerald-500/10',
      iconColor: 'text-emerald-600',
      textColor: 'text-slate-600'
    },
    purple: {
      gradient: 'gradient-bg-1',
      iconBg: 'bg-purple-500/10',
      iconColor: 'text-purple-600',
      textColor: 'text-slate-600'
    },
    orange: {
      gradient: 'gradient-bg-2',
      iconBg: 'bg-orange-500/10',
      iconColor: 'text-orange-600',
      textColor: 'text-slate-600'
    },
    red: {
      gradient: 'from-red-400 to-red-600',
      iconBg: 'bg-red-500/10',
      iconColor: 'text-red-600',
      textColor: 'text-slate-600'
    }
  }

  const config = colorConfig[color]

  return (
    <div className="stat-card slide-up">
      <div className="relative z-10">
        <div className="flex items-center justify-between mb-4">
          <div className={`w-12 h-12 rounded-2xl ${config.iconBg} flex items-center justify-center`}>
            <Icon className={`w-6 h-6 ${config.iconColor}`} />
          </div>
          {change && (
            <div className={`flex items-center text-sm font-medium ${
              change.type === 'increase' ? 'text-emerald-600' : 'text-red-500'
            }`}>
              <span className="mr-1">
                {change.type === 'increase' ? '↑' : '↓'}
              </span>
              {Math.abs(change.value)}%
            </div>
          )}
        </div>

        <div className="mb-2">
          <div className="text-3xl font-bold text-slate-900 mb-1">
            {typeof value === 'number' ? value.toLocaleString() : value}
          </div>
          <div className={`text-sm font-medium ${config.textColor}`}>
            {title}
          </div>
        </div>
      </div>
    </div>
  )
}