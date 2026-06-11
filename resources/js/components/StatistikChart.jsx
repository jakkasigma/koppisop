"use client"
import * as React from "react"
import * as ReactDOM from "react-dom"
import { Area, AreaChart, CartesianGrid, XAxis, ResponsiveContainer } from "recharts"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "./ui/card"
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from "./ui/chart"

/* ── helpers ─────────────────────────────────────────────── */
function formatRupiahFull(value) {
  return new Intl.NumberFormat("id-ID", {
    style: "currency", currency: "IDR",
    minimumFractionDigits: 0, maximumFractionDigits: 0,
  }).format(value)
}

function formatDateLabel(dateStr) {
  if (!dateStr || dateStr.length <= 7) return dateStr
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric" })
}

function formatDateFull(dateStr) {
  if (!dateStr || dateStr.length <= 7) return dateStr
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return d.toLocaleDateString("id-ID", {
    weekday: "short", day: "numeric", month: "short", year: "numeric",
  })
}

function parseYMD(str) {
  if (!str) return null
  const d = new Date(str)
  return isNaN(d) ? null : d
}

function sliceByDays(arr, days) {
  if (!arr.length) return arr
  const last = arr[arr.length - 1]?.date
  if (!last) return arr
  const ref = new Date(last)
  const start = new Date(ref)
  start.setDate(start.getDate() - days + 1)
  return arr.filter(d => new Date(d.date) >= start)
}

function sliceByMonths(arr, months) {
  if (!arr.length) return arr
  const last = arr[arr.length - 1]?.date
  if (!last) return arr
  const ref = new Date(last)
  const start = new Date(ref)
  start.setMonth(start.getMonth() - months + 1)
  start.setDate(1)
  return arr.filter(d => new Date(d.date) >= start)
}

function sliceByFilter(arr, awal, akhir) {
  if (!arr.length) return arr
  const from = parseYMD(awal)
  const to   = parseYMD(akhir)
  if (!from && !to) return arr
  return arr.filter(d => {
    const dt = new Date(d.date)
    if (from && dt < from) return false
    if (to   && dt > to)   return false
    return true
  })
}

/* ── default palette ─────────────────────────────────────── */
const METHOD_PALETTE = {
  cash:       "hsl(142 60% 45%)",
  qris:       "hsl(198 85% 48%)",
  debit:      "hsl(38 90% 50%)",
  shopeefood: "hsl(20 100% 55%)",
  gofood:     "hsl(142 60% 45%)",
  grabfood:   "hsl(217 85% 55%)",
  delivery:   "hsl(271 75% 55%)",
}

/* ── DropdownSelect ──────────────────────────────────────── */
function DropdownSelect({ options, value, onChange }) {
  const [open, setOpen] = React.useState(false)
  const btnRef  = React.useRef(null)
  const menuRef = React.useRef(null)
  const current = options.find(o => o.value === value)

  // Close on outside click
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

  // Portal position — fixed relative to viewport so no clipping
  const [menuStyle, setMenuStyle] = React.useState(null)
  React.useLayoutEffect(() => {
    if (!open || !btnRef.current) return
    const r    = btnRef.current.getBoundingClientRect()
    const minW = Math.max(r.width, 210)
    let left   = r.right - minW
    if (left < 8) left = 8
    if (left + minW > window.innerWidth - 8) left = window.innerWidth - minW - 8
    // flip up if not enough space below
    const spaceBelow = window.innerHeight - r.bottom
    const menuH      = options.length * 40 + 8
    const top = spaceBelow > menuH ? r.bottom + 6 : r.top - menuH - 6
    setMenuStyle({
      position:     "fixed",
      top,
      left,
      minWidth:     minW,
      zIndex:       9999,
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
      {/* Trigger */}
      <button
        ref={btnRef}
        type="button"
        onClick={() => setOpen(v => !v)}
        style={{
          display: "inline-flex", alignItems: "center", gap: "6px",
          padding: "6px 12px", borderRadius: "8px",
          border: "1px solid var(--border)",
          background: "var(--background)",
          color: "var(--foreground)",
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

      {/* Menu — rendered into body via portal to avoid any overflow:hidden clipping */}
      {open && menuStyle && ReactDOM.createPortal(
        <div ref={menuRef} style={menuStyle}>
          {options.map(opt => {
            const isActive = opt.value === value
            return (
              <button
                key={opt.value}
                type="button"
                onClick={() => { onChange(opt.value); setOpen(false) }}
                style={{
                  display:        "flex",
                  alignItems:     "center",
                  justifyContent: "space-between",
                  width:          "100%",
                  padding:        "8px 12px",
                  borderRadius:   "0.5rem",
                  border:         "none",
                  cursor:         "pointer",
                  textAlign:      "left",
                  gap:            "8px",
                  fontSize:       "13px",
                  fontWeight:     isActive ? 700 : 400,
                  background:     isActive ? "color-mix(in oklch, var(--accent, var(--primary)) 12%, transparent)" : "transparent",
                  color:          isActive ? "var(--foreground)" : "var(--foreground)",
                }}
                onMouseEnter={e => {
                  if (!isActive) e.currentTarget.style.background = "var(--muted)"
                }}
                onMouseLeave={e => {
                  if (!isActive) e.currentTarget.style.background = "transparent"
                }}
              >
                <span>{opt.label}</span>
                {isActive && (
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"
                    style={{ flexShrink: 0, color: "var(--foreground)", opacity: 0.8 }}>
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

/* ════════════════════════════════════════════════════════════
   StatistikChart
   Props:
     dailyData   : [{ date, omzet, transaksi }]
     monthlyData : [{ date, omzet, transaksi }]
     methodData  : [{ date, cash, qris, debit, ... }]  — harian per-metode merged
     methodKeys  : ["cash","qris","debit"]
     methodLabels: { cash:"Cash", ... }
     methodColors: { cash:"#...", ... }
     tanggalAwal / tanggalAkhir : string  — dari page filter (untuk opsi "Sesuai Filter")
════════════════════════════════════════════════════════════ */
export function StatistikChart({
  dailyData    = [],
  monthlyData  = [],
  methodData   = [],
  methodKeys   = [],
  methodLabels = {},
  methodColors = {},
  tanggalAwal  = "",
  tanggalAkhir = "",
}) {
  // "daily" | "monthly"
  const [chartMode,   setChartMode]   = React.useState("daily")
  // "total" | "method"
  const [revenueMode, setRevenueMode] = React.useState("total")
  // "90d" | "30d" | "7d" | "filter"
  const [timeRange,   setTimeRange]   = React.useState("30d")

  /* ── normalise ──────────────────────────────────────────── */
  const normDaily = React.useMemo(() =>
    dailyData.map(r => ({
      date:      r.date || r.label || "",
      omzet:     Number(r.omzet ?? 0),
      transaksi: Number(r.transaksi ?? 0),
    }))
  , [dailyData])

  const normMonthly = React.useMemo(() =>
    monthlyData.map(r => {
      const point = {
        date:      r.date || r.label || "",
        label:     r.label || r.date || "",
        omzet:     Number(r.omzet ?? 0),
        transaksi: Number(r.transaksi ?? 0),
      }
      // carry through all method keys
      if (methodKeys && methodKeys.length) {
        methodKeys.forEach(k => { point[k] = Number(r[k] ?? 0) })
      }
      return point
    })
  , [monthlyData, methodKeys])

  /* ── time range options — label sesuai mode ─────────────── */
  const timeRangeOptions = React.useMemo(() => {
    const isMonthly = chartMode === "monthly"
    const base = isMonthly
      ? [
          { value: "90d", label: "12 bulan terakhir" },
          { value: "30d", label: "3 bulan terakhir"  },
          { value: "7d",  label: "1 bulan terakhir"  },
        ]
      : [
          { value: "90d", label: "90 hari terakhir" },
          { value: "30d", label: "30 hari terakhir" },
          { value: "7d",  label: "7 hari terakhir"  },
        ]
    if (tanggalAwal && tanggalAkhir) {
      base.push({ value: "filter", label: "Sesuai Filter" })
    }
    return base
  }, [chartMode, tanggalAwal, tanggalAkhir])

  /* ── slice helper for daily ─────────────────────────────── */
  function applyTimeRange(arr) {
    if (timeRange === "filter") return sliceByFilter(arr, tanggalAwal, tanggalAkhir)
    const days = timeRange === "7d" ? 7 : timeRange === "30d" ? 30 : 90
    return sliceByDays(arr, days)
  }

  /* ── slice helper for monthly ──────────────────────────── */
  function applyTimeRangeMonthly(arr) {
    if (timeRange === "filter" && tanggalAwal && tanggalAkhir) {
      // date is ISO "YYYY-MM-01" so direct date comparison works
      return sliceByFilter(arr, tanggalAwal, tanggalAkhir)
    }
    const monthsMap = { "7d": 1, "30d": 3, "90d": 12 }
    const months = monthsMap[timeRange] ?? 12
    return sliceByMonths(arr, months)
  }

  /* ── active data ────────────────────────────────────────── */
  const activeData = React.useMemo(() => {
    if (chartMode === "monthly") {
      return applyTimeRangeMonthly(normMonthly)
    }
    // Daily mode
    if (revenueMode === "method") return applyTimeRange(methodData)
    return applyTimeRange(normDaily)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chartMode, revenueMode, normDaily, normMonthly, methodData, timeRange, tanggalAwal, tanggalAkhir])

  /* ── chart config ───────────────────────────────────────── */
  const isMethodMode = revenueMode === "method"

  const chartConfig = React.useMemo(() => {
    if (isMethodMode) {
      const cfg = {}
      methodKeys.forEach(k => {
        cfg[k] = {
          label: methodLabels[k] || k.charAt(0).toUpperCase() + k.slice(1),
          color: methodColors[k] || METHOD_PALETTE[k] || "var(--chart-1)",
        }
      })
      return cfg
    }
    return {
      omzet:     { label: "Omzet",     color: "var(--chart-1)" },
      transaksi: { label: "Transaksi", color: "var(--chart-2)" },
    }
  }, [isMethodMode, methodKeys, methodLabels, methodColors])

  const hasTransaksi = !isMethodMode && activeData.some(d => d.transaksi > 0)

  /* ── subtitle ───────────────────────────────────────────── */
  const description = React.useMemo(() => {
    if (timeRange === "filter" && tanggalAwal && tanggalAkhir)
      return `${tanggalAwal} s/d ${tanggalAkhir}`
    if (chartMode === "monthly") {
      const map = { "7d": "1 bulan", "30d": "3 bulan", "90d": "12 bulan" }
      return `${map[timeRange] || "12 bulan"} terakhir`
    }
    const map = { "90d": "90 hari", "30d": "30 hari", "7d": "7 hari" }
    return `${map[timeRange] || "30 hari"} terakhir`
  }, [chartMode, timeRange, tanggalAwal, tanggalAkhir])

  /* ── revenue mode options ───────────────────────────────── */
  const revenueModeOptions = [
    { value: "total",  label: "Total Pendapatan" },
    { value: "method", label: "Per Metode Bayar"  },
  ]

  /* ── chart title ────────────────────────────────────────── */
  const chartTitle = isMethodMode
    ? "Omzet per Metode Pembayaran"
    : "Area Chart — Omzet Cafe"

  /* ── colors ─────────────────────────────────────────────── */
  const colorOmzet     = "var(--color-omzet, var(--chart-1))"
  const colorTransaksi = "var(--color-transaksi, var(--chart-2))"

  return (
    <>
      <style>{`
        @keyframes sc-dd-in {
          from { opacity: 0; transform: translateY(4px) scale(0.97); }
          to   { opacity: 1; transform: translateY(0)   scale(1); }
        }
      `}</style>

      <Card style={{ paddingTop: 0, width: "100%" }}>

        {/* ════════ Header ════════════════════════════════════ */}
        <CardHeader style={{
          display: "flex",
          flexDirection: "row",
          alignItems: "center",
          gap: "12px",
          borderBottom: "1px solid var(--border)",
          padding: "16px 20px",
          flexWrap: "wrap",
        }}>

          {/* Title block */}
          <div style={{ flex: 1, minWidth: 0 }}>
            <CardTitle style={{ fontSize: "15px", fontWeight: 700 }}>
              {chartTitle}
            </CardTitle>
            <CardDescription style={{ marginTop: "3px" }}>
              {description}
            </CardDescription>
          </div>

          {/* ── Controls (right side) ───────────────────────── */}
          <div style={{
            display: "flex", gap: "6px", alignItems: "center",
            flexShrink: 0, flexWrap: "wrap",
          }}>

            {/* 1. Revenue mode dropdown — always visible */}
            <DropdownSelect
              options={revenueModeOptions}
              value={revenueMode}
              onChange={setRevenueMode}
            />

            {/* 2. Harian / Bulanan toggle */}
            <div style={{
              display: "flex",
              border: "1px solid var(--border)",
              borderRadius: "8px",
              overflow: "hidden",
            }}>
              {[
                { v: "daily",   label: "Harian"  },
                { v: "monthly", label: "Bulanan" },
              ].map((opt, i) => (
                <button
                  key={opt.v}
                  type="button"
                  onClick={() => {
                    setChartMode(opt.v)
                    // Reset ke default yang masuk akal saat ganti mode
                    if (opt.v === "monthly" && timeRange === "7d") setTimeRange("90d")
                    if (opt.v === "daily"   && timeRange === "90d") setTimeRange("30d")
                  }}
                  style={{
                    padding: "6px 14px", fontSize: "13px", fontWeight: 600,
                    cursor: "pointer", border: "none",
                    borderRight: i === 0 ? "1px solid var(--border)" : "none",
                    background: chartMode === opt.v
                      ? "var(--primary)"
                      : "var(--background)",
                    color: chartMode === opt.v
                      ? "var(--primary-foreground)"
                      : "var(--foreground)",
                    transition: "background 0.12s, color 0.12s",
                  }}
                >
                  {opt.label}
                </button>
              ))}
            </div>

            {/* 3. Time range dropdown — always visible */}
            <DropdownSelect
              options={timeRangeOptions}
              value={timeRange}
              onChange={setTimeRange}
            />
          </div>
        </CardHeader>

        {/* ════════ Chart ═════════════════════════════════════ */}
        <CardContent style={{ padding: "16px 8px 8px" }}>
          {activeData.length === 0 ? (
            <div style={{
              height: "420px", display: "flex", alignItems: "center",
              justifyContent: "center", opacity: 0.4, fontSize: "14px",
              color: "var(--muted-foreground)",
            }}>
              Belum ada data untuk ditampilkan
            </div>
          ) : (
            <ChartContainer config={chartConfig} style={{ width: "100%", height: "420px" }}>
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={activeData} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>

                  <defs>
                    {isMethodMode
                      ? methodKeys.map(k => {
                          const col = methodColors[k] || METHOD_PALETTE[k] || "var(--chart-1)"
                          return (
                            <linearGradient key={k} id={`sgFill_${k}`} x1="0" y1="0" x2="0" y2="1">
                              <stop offset="5%"  stopColor={col} stopOpacity={0.75} />
                              <stop offset="95%" stopColor={col} stopOpacity={0.05} />
                            </linearGradient>
                          )
                        })
                      : <>
                          <linearGradient id="sgFillOmzet" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%"  stopColor={colorOmzet}     stopOpacity={0.8} />
                            <stop offset="95%" stopColor={colorOmzet}     stopOpacity={0.05} />
                          </linearGradient>
                          <linearGradient id="sgFillTrx" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%"  stopColor={colorTransaksi} stopOpacity={0.8} />
                            <stop offset="95%" stopColor={colorTransaksi} stopOpacity={0.05} />
                          </linearGradient>
                        </>
                    }
                  </defs>

                  <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="var(--border)"
                    vertical={false}
                  />

                  <XAxis
                    dataKey="date"
                    tickLine={false} axisLine={false} tickMargin={8} minTickGap={32}
                    tick={{ fontSize: 12, fill: "var(--muted-foreground)" }}
                    tickFormatter={v => chartMode === "monthly" ? (activeData.find(d => d.date === v)?.label ?? v) : formatDateLabel(v)}
                  />

                  <ChartTooltip
                    cursor={false}
                    content={
                      <ChartTooltipContent
                        labelFormatter={v => chartMode === "monthly"
                          ? (activeData.find(d => d.date === v)?.label ?? v)
                          : formatDateFull(v)
                        }
                        formatter={(value, key) =>
                          key === "transaksi" ? `${value} trx` : formatRupiahFull(value)
                        }
                        indicator="dot"
                      />
                    }
                  />

                  {isMethodMode
                    ? methodKeys.map(k => {
                        const col = methodColors[k] || METHOD_PALETTE[k] || "var(--chart-1)"
                        return (
                          <Area
                            key={k}
                            dataKey={k}
                            type="natural"
                            fill={`url(#sgFill_${k})`}
                            stroke={col}
                            strokeWidth={2}
                            dot={false}
                          />
                        )
                      })
                    : <>
                        <Area
                          dataKey="omzet"
                          type="natural"
                          fill="url(#sgFillOmzet)"
                          stroke={colorOmzet}
                          strokeWidth={2}
                          stackId="a"
                        />
                        {hasTransaksi && (
                          <Area
                            dataKey="transaksi"
                            type="natural"
                            fill="url(#sgFillTrx)"
                            stroke={colorTransaksi}
                            strokeWidth={2}
                            stackId="a"
                          />
                        )}
                      </>
                  }

                  <ChartLegend content={<ChartLegendContent />} />
                </AreaChart>
              </ResponsiveContainer>
            </ChartContainer>
          )}
        </CardContent>
      </Card>
    </>
  )
}
