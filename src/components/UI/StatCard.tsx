import { LucideIcon } from 'lucide-react'

interface StatCardProps {
  title: string
  value: number | string
  icon: LucideIcon
  gradient?: string
}

export function StatCard({ title, value, icon: Icon, gradient }: StatCardProps) {
  const defaultGradient = 'from-blue-500 to-purple-600'
  
  return (
    <div className={`dashboard-card bg-gradient-to-br ${gradient || defaultGradient}`}>
      <div className="flex justify-between items-center">
        <div>
          <div className="text-3xl font-bold mb-2">{value}</div>
          <div className="text-blue-100">{title}</div>
        </div>
        <div className="opacity-80">
          <Icon className="w-12 h-12" />
        </div>
      </div>
    </div>
  )
}