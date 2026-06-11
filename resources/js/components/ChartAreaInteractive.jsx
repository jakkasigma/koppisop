import * as React from "react"
import * as ReactDOM from "react-dom"
import {
  Area,
  AreaChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "./ui/card"

const chartConfig = {
  omzet: {
    label: "Omzet",
    color: "var(--chart-1)",
  },
  transaksi: {
    label: "Transaksi",
    color: "var(--chart-2)",
  },
}

function formatRupiah(value) {
  if (value >= 1_000_000) {
    return "Rp " + (value / 1_000_000).toFixed(1).replace(/\.0$/, "") + "jt"
  }
  if (value >= 1_000) {
    return "Rp " + (value / 1_000).toFixed(0) + "rb"
  }
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
  }).format(value)
}

function formatRupiahFull(value) {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
  }).format(value)
}

function formatDate(dateStr) {
  const date = new Date(dateStr)
  return date.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
  })
}

function CustomTooltip({ active, payload, label }) {
  if (!active || !payload || !payload.length) return null

  return (
    <div
      style={{
        background: "var(--card, #fff)",
        border: "1px solid var(--border, #e2e8f0)",
        borderRadius: "8px",
        padding: "12px 16px",
        boxShadow: "0 4px 16px rgba(0,0,0,0.12)",
        minWidth: "160px",
      }}
    >
      <p style={{ fontWeight: 600, marginBottom: "8px", fontSize: "13px" }}>
        {formatDate(label)}
      </p>
      {payload.map((entry, index) => (
        <div
          key={index}
          style={{
            display: "flex",
            alignItems: "center",
            gap: "8px",
            fontSize: "13px",
            marginBottom: index < payload.length - 1 ? "4px" : 0,
          }}
        >
          <span
            style={{
              display: "inline-block",
              width: "8px",
              height: "8px",
              borderRadius: "50%",
              background: entry.color,
              flexShrink: 0,
            }}
          />
          <span style={{ opacity: 0.7 }}>{entry.name}:</span>
          <span style={{ fontWeight: 600 }}>
            {entry.name === "Omzet"
              ? formatRupiahFull(entry.value)
              : entry.value + " trx"}
          </span>
        </div>
      ))}
    </div>
  )
}

function CustomLegend({ payload }) {
  if (!payload || !payload.length) return null
  return (
    <div
      style={{
        display: "flex",
        justifyContent: "center",
        gap: "20px",
        paddingTop: "12px",
      }}
    >
      {payload.map((entry, index) => (
        <div
          key={index}
          style={{ display: "flex", alignItems: "center", gap: "6px", fontSize: "13px" }}
        >
          <span
            style={{
              display: "inline-block",
              width: "8px",
              height: "8px",
              borderRadius: "50%",
              background: entry.color,
            }}
          />
          <span style={{ opacity: 0.8 }}>{entry.value}</span>
        </div>
      ))}
    </div>
  )
}

/* ── Custom dropdown (portal ke body, tidak ter-clip) ──────── */
function DropdownSelect({ options, value, onChange }) {
  const [open, setOpen]       = React.useState(false)
  const [style, setStyle]     = React.useState(null)
  const btnRef  = React.useRef(null)
  const menuRef = React.useRef(null)
  const current = options.find(o => o.value === value)

  React.useEffect(() => {
    if (!open) return
    const fn = e => {
      if (
        btnRef.current  && !btnRef.current.contains(e.target) &&
        menuRef.current && !menuRef.current.contains(e.target)
      ) setOpen(false)
    }
    document.addEventListener("mousedown", fn)
    return () => document.removeEventListener("mousedown", fn)
  }, [open])

  React.useLayoutEffect(() => {
    if (!open || !btnRef.current) return
    const r    = btnRef.current.getBoundingClientRect()
    const minW = Math.max(r.width, 200)
    let left   = r.right - minW
    if (left < 8) left = 8
    if (left + minW > window.innerWidth - 8) left = window.innerWidth - minW - 8
    const spaceBelow = window.innerHeight - r.bottom
    const menuH = options.length * 40 + 8
    const top = spaceBelow > menuH ? r.bottom + 6 : r.top - menuH - 6
    setStyle({
      position: "fixed", top, left, minWidth: minW, zIndex: 9999,
      background:   "var(--popover, var(--card))",
      color:        "var(--popover-foreground, var(--foreground))",
      border:       "1px solid var(--border)",
      borderRadius: "0.75rem",
      boxShadow:    "0 8px 32px rgba(0,0,0,0.22)",
      padding:      "4px",
    })
  }, [open, options.length])

  return (
    <div style={{ position: "relative" }}>
      <button
        ref={btnRef}
        type="button"
        onClick={() => setOpen(v => !v)}
        style={{
          display: "inline-flex", alignItems: "center", gap: "6px",
          padding: "6px 12px", borderRadius: "8px",
          border: "1px solid var(--border, #e2e8f0)",
          background: "var(--background, #fff)",
          color: "var(--foreground, #0f172a)",
          fontSize: "13px", fontWeight: 500, cursor: "pointer",
          whiteSpace: "nowrap", outline: "none",
        }}
      >
        <span>{current?.label ?? value}</span>
        <svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"
          style={{ opacity: 0.5, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.15s" }}>
          <path d="M2 4l4 4 4-4" stroke="currentColor" strokeWidth="1.6"
            strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </button>

      {open && style && ReactDOM.createPortal(
        <div ref={menuRef} style={style}>
          {options.map(opt => {
            const isActive = opt.value === value
            return (
              <button
                key={opt.value}
                type="button"
                onClick={() => { onChange(opt.value); setOpen(false) }}
                style={{
                  display: "flex", alignItems: "center", justifyContent: "space-between",
                  width: "100%", padding: "8px 12px", borderRadius: "0.5rem",
                  border: "none", cursor: "pointer", textAlign: "left", gap: "8px",
                  fontSize: "13px", fontWeight: isActive ? 700 : 400,
                  background: isActive ? "var(--muted)" : "transparent",
                  color: "var(--foreground, #0f172a)",
                }}
                onMouseEnter={e => { if (!isActive) e.currentTarget.style.background = "var(--muted)" }}
                onMouseLeave={e => { if (!isActive) e.currentTarget.style.background = "transparent" }}
              >
                <span>{opt.label}</span>
                {isActive && (
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"
                    style={{ flexShrink: 0, opacity: 0.8 }}>
                    <path d="M2.5 7l3 3L11.5 4" stroke="currentColor" strokeWidth="2"
                      strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                )}
              </button>
            )
          })}
        </div>,
        document.body
      )}
    </div>
  )
}

export function ChartAreaInteractive({
  data = [],
  title = "Area Chart - Pendapatan",
  description = "Omzet dan transaksi operasional",
  statsUrl = null,
}) {
  const [timeRange, setTimeRange] = React.useState("30d")

  // Normalise data — backend sends { date, value, transaksi } or { date, omzet, transaksi }
  const normalisedData = React.useMemo(() => {
    return data.map((item) => ({
      date: item.date,
      omzet: Number(item.omzet ?? item.value ?? 0),
      transaksi: Number(item.transaksi ?? 0),
    }))
  }, [data])

  const filteredData = React.useMemo(() => {
    if (!normalisedData.length) return []

    const lastDate = normalisedData[normalisedData.length - 1]?.date
    if (!lastDate) return normalisedData

    const referenceDate = new Date(lastDate)
    const daysMap = { "90d": 90, "30d": 30, "7d": 7 }
    const daysToSubtract = daysMap[timeRange] ?? 30

    const startDate = new Date(referenceDate)
    startDate.setDate(startDate.getDate() - daysToSubtract)

    return normalisedData.filter((item) => new Date(item.date) >= startDate)
  }, [normalisedData, timeRange])

  const labelMap = {
    "90d": "90 hari terakhir",
    "30d": "30 hari terakhir",
    "7d": "7 hari terakhir",
  }

  return (
    <Card style={{ paddingTop: 0, width: "100%" }}>
      {/* ── Header ── */}
      <CardHeader
        style={{
          display: "flex",
          flexDirection: "row",
          alignItems: "center",
          gap: "12px",
          borderBottom: "1px solid var(--border, #e2e8f0)",
          padding: "20px 24px",
          flexWrap: "wrap",
        }}
      >
        <div style={{ flex: 1, minWidth: 0 }}>
          <CardTitle style={{ fontSize: "16px", fontWeight: 600 }}>
            {title}
          </CardTitle>
          <CardDescription style={{ marginTop: "2px" }}>
            {filteredData.length > 0
              ? `Menampilkan data ${labelMap[timeRange]}`
              : description}
          </CardDescription>
        </div>

        <div style={{ display: "flex", gap: "8px", alignItems: "center", flexShrink: 0 }}>
          {/* Custom dropdown time range */}
          <DropdownSelect
            options={[
              { value: "90d", label: "90 hari terakhir" },
              { value: "30d", label: "30 hari terakhir" },
              { value: "7d",  label: "7 hari terakhir"  },
            ]}
            value={timeRange}
            onChange={setTimeRange}
          />

          {statsUrl && (
            <a
              href={statsUrl}
              style={{
                padding: "6px 14px",
                borderRadius: "8px",
                border: "1px solid var(--border, #e2e8f0)",
                background: "transparent",
                fontSize: "13px",
                textDecoration: "none",
                color: "var(--foreground, #0f172a)",
                fontWeight: 500,
                whiteSpace: "nowrap",
              }}
            >
              Lihat Statistik
            </a>
          )}
        </div>
      </CardHeader>

      {/* ── Chart ── */}
      <CardContent style={{ padding: "16px 8px 8px" }}>
        {filteredData.length === 0 ? (
          <div
            style={{
              height: "250px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              opacity: 0.4,
              fontSize: "14px",
            }}
          >
            Belum ada data untuk ditampilkan
          </div>
        ) : (
          <ResponsiveContainer width="100%" height={250}>
            <AreaChart
              data={filteredData}
              margin={{ top: 8, right: 16, left: 0, bottom: 0 }}
            >
              <defs>
                <linearGradient id="fillOmzet" x1="0" y1="0" x2="0" y2="1">
                  <stop
                    offset="5%"
                    stopColor={chartConfig.omzet.color}
                    stopOpacity={0.8}
                  />
                  <stop
                    offset="95%"
                    stopColor={chartConfig.omzet.color}
                    stopOpacity={0.05}
                  />
                </linearGradient>
                <linearGradient id="fillTransaksi" x1="0" y1="0" x2="0" y2="1">
                  <stop
                    offset="5%"
                    stopColor={chartConfig.transaksi.color}
                    stopOpacity={0.8}
                  />
                  <stop
                    offset="95%"
                    stopColor={chartConfig.transaksi.color}
                    stopOpacity={0.05}
                  />
                </linearGradient>
              </defs>

              <CartesianGrid
                strokeDasharray="3 3"
                stroke="var(--border, #e2e8f0)"
                vertical={false}
              />

              <XAxis
                dataKey="date"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={32}
                tickFormatter={formatDate}
                tick={{ fontSize: 12, fill: "var(--muted-foreground, #64748b)" }}
              />

              <YAxis
                yAxisId="omzet"
                orientation="left"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                tickFormatter={formatRupiah}
                tick={{ fontSize: 11, fill: "var(--muted-foreground, #64748b)" }}
                width={56}
              />

              <Tooltip content={<CustomTooltip />} cursor={false} />

              <Legend content={<CustomLegend />} />

              {/* Omzet area — stacked on bottom */}
              <Area
                yAxisId="omzet"
                type="natural"
                dataKey="omzet"
                name="Omzet"
                stroke={chartConfig.omzet.color}
                strokeWidth={2}
                fill="url(#fillOmzet)"
                stackId="a"
              />

              {/* Transaksi area — only show if data has transaksi values */}
              {filteredData.some((d) => d.transaksi > 0) && (
                <Area
                  yAxisId="omzet"
                  type="natural"
                  dataKey="transaksi"
                  name="Transaksi"
                  stroke={chartConfig.transaksi.color}
                  strokeWidth={2}
                  fill="url(#fillTransaksi)"
                  stackId="a"
                />
              )}
            </AreaChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  )
}
