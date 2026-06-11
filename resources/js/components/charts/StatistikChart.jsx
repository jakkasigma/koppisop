import * as React from "react"
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "../ui/card"
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from "../ui/chart"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../ui/select"

const chartConfig = {
  omzet: {
    label: "Omzet",
    color: "hsl(220 70% 50%)",
  },
  transaksi: {
    label: "Transaksi",
    color: "hsl(340 75% 55%)",
  },
}

export function StatistikChart({ dailyData = [], monthlyData = [] }) {
  const [timeRange, setTimeRange] = React.useState("daily")
  const [viewMode, setViewMode] = React.useState("omzet")

  const displayData = React.useMemo(() => {
    const sourceData = timeRange === "daily" ? dailyData : monthlyData

    return sourceData.map(item => ({
      date: item.label || item.date,
      value: viewMode === "omzet" ? Number(item.omzet || 0) : Number(item.transaksi || 0),
      label: item.label,
      omzet: Number(item.omzet || 0),
      transaksi: Number(item.transaksi || 0),
    }))
  }, [dailyData, monthlyData, timeRange, viewMode])

  const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value)
  }

  return (
    <Card className="w-full">
      <CardHeader className="flex flex-col sm:flex-row items-start sm:items-center gap-4 space-y-0 border-b py-5">
        <div className="flex-1">
          <CardTitle>Statistik Penjualan - React Chart</CardTitle>
          <CardDescription>
            Visualisasi interaktif menggunakan Recharts
          </CardDescription>
        </div>
        <div className="flex gap-2 flex-wrap">
          <Select value={timeRange} onValueChange={setTimeRange}>
            <SelectTrigger className="w-[140px]">
              <SelectValue placeholder="Pilih periode" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="daily">Harian</SelectItem>
              <SelectItem value="monthly">Bulanan</SelectItem>
            </SelectContent>
          </Select>

          <Select value={viewMode} onValueChange={setViewMode}>
            <SelectTrigger className="w-[140px]">
              <SelectValue placeholder="Pilih tampilan" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="omzet">Omzet</SelectItem>
              <SelectItem value="transaksi">Transaksi</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </CardHeader>
      <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
        <ChartContainer
          config={chartConfig}
          className="aspect-auto h-[300px] w-full"
        >
          <AreaChart data={displayData}>
            <defs>
              <linearGradient id="fillValue" x1="0" y1="0" x2="0" y2="1">
                <stop
                  offset="5%"
                  stopColor={viewMode === "omzet" ? "hsl(220 70% 50%)" : "hsl(340 75% 55%)"}
                  stopOpacity={0.8}
                />
                <stop
                  offset="95%"
                  stopColor={viewMode === "omzet" ? "hsl(220 70% 50%)" : "hsl(340 75% 55%)"}
                  stopOpacity={0.1}
                />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              tick={{ fontSize: 12 }}
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              tick={{ fontSize: 12 }}
              tickFormatter={(value) => {
                if (viewMode === "omzet") {
                  return value >= 1000000 ? `${(value / 1000000).toFixed(0)}M` : `${(value / 1000).toFixed(0)}K`
                }
                return value.toString()
              }}
            />
            <ChartTooltip
              content={({ active, payload }) => {
                if (!active || !payload?.length) return null
                const data = payload[0].payload
                return (
                  <div className="rounded-lg border bg-background p-2 shadow-md">
                    <div className="font-semibold">{data.date}</div>
                    <div className="text-sm">
                      <span className="text-muted-foreground">Omzet: </span>
                      <span className="font-medium">{formatRupiah(data.omzet)}</span>
                    </div>
                    <div className="text-sm">
                      <span className="text-muted-foreground">Transaksi: </span>
                      <span className="font-medium">{data.transaksi}</span>
                    </div>
                  </div>
                )
              }}
            />
            <Area
              dataKey="value"
              type="monotone"
              fill="url(#fillValue)"
              stroke={viewMode === "omzet" ? "hsl(220 70% 50%)" : "hsl(340 75% 55%)"}
              strokeWidth={2}
            />
          </AreaChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
