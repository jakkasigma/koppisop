/**
 * shadcn/ui Chart primitives — full implementation
 * Wraps Recharts with CSS variable theming + tooltip/legend helpers
 */
import * as React from "react"
import { Tooltip as RechartsTooltip, Legend as RechartsLegend, ResponsiveContainer } from "recharts"

/* ── context ───────────────────────────────────────────── */
const ChartContext = React.createContext(null)

function useChart() {
  const ctx = React.useContext(ChartContext)
  if (!ctx) throw new Error("useChart must be used within ChartContainer")
  return ctx
}

/* ── CSS variable injection ─────────────────────────────── */
function buildCssVars(config) {
  const vars = {}
  Object.entries(config).forEach(([key, value]) => {
    if (value && value.color) {
      vars[`--color-${key}`] = value.color
    }
  })
  return vars
}

/* ── ChartContainer ─────────────────────────────────────── */
const ChartContainer = React.forwardRef(
  ({ config = {}, children, className = "", style = {}, ...props }, ref) => {
    const cssVars = buildCssVars(config)
    return (
      <ChartContext.Provider value={{ config }}>
        <div
          ref={ref}
          data-chart
          className={className}
          style={{ ...cssVars, ...style }}
          {...props}
        >
          {children}
        </div>
      </ChartContext.Provider>
    )
  }
)
ChartContainer.displayName = "ChartContainer"

/* ── ChartTooltip ───────────────────────────────────────── */
const ChartTooltip = RechartsTooltip
ChartTooltip.displayName = "ChartTooltip"

/* ── ChartTooltipContent ─────────────────────────────────── */
const ChartTooltipContent = React.forwardRef(
  (
    {
      active,
      payload,
      label,
      labelFormatter,
      formatter,
      indicator = "dot",
      hideLabel = false,
      hideIndicator = false,
      className = "",
    },
    ref
  ) => {
    const { config } = useChart()

    if (!active || !payload || !payload.length) return null

    const displayLabel = labelFormatter ? labelFormatter(label, payload) : label

    return (
      <div
        ref={ref}
        style={{
          background: "var(--popover)",
          border: "1px solid var(--border)",
          borderRadius: "8px",
          padding: "10px 14px",
          boxShadow: "0 4px 20px rgba(0,0,0,0.18)",
          fontSize: "13px",
          minWidth: "160px",
          color: "var(--popover-foreground)",
        }}
      >
        {!hideLabel && displayLabel && (
          <div
            style={{
              fontWeight: 700,
              marginBottom: "8px",
              paddingBottom: "6px",
              borderBottom: "1px solid var(--border)",
              color: "var(--foreground)",
            }}
          >
            {displayLabel}
          </div>
        )}
        <div style={{ display: "grid", gap: "5px" }}>
          {payload.map((entry, index) => {
            const itemConfig = config[entry.dataKey] || {}
            const color = entry.color || itemConfig.color || "var(--chart-1)"
            const itemLabel = itemConfig.label || entry.name || entry.dataKey
            const formattedValue = formatter
              ? formatter(entry.value, entry.dataKey, entry, index)
              : entry.value

            return (
              <div
                key={`item-${index}`}
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "8px",
                  fontSize: "13px",
                }}
              >
                {!hideIndicator && (
                  <>
                    {indicator === "dot" && (
                      <span
                        style={{
                          display: "inline-block",
                          width: "8px",
                          height: "8px",
                          borderRadius: "50%",
                          background: color,
                          flexShrink: 0,
                        }}
                      />
                    )}
                    {indicator === "line" && (
                      <span
                        style={{
                          display: "inline-block",
                          width: "16px",
                          height: "2px",
                          background: color,
                          flexShrink: 0,
                        }}
                      />
                    )}
                    {indicator === "dashed" && (
                      <span
                        style={{
                          display: "inline-block",
                          width: "16px",
                          height: "2px",
                          background: color,
                          opacity: 0.7,
                          flexShrink: 0,
                        }}
                      />
                    )}
                  </>
                )}
                <span style={{ color: "var(--muted-foreground)", flex: 1 }}>
                  {itemLabel}
                </span>
                <span style={{ fontWeight: 700, color: "var(--foreground)" }}>
                  {formattedValue}
                </span>
              </div>
            )
          })}
        </div>
      </div>
    )
  }
)
ChartTooltipContent.displayName = "ChartTooltipContent"

/* ── ChartLegend ────────────────────────────────────────── */
const ChartLegend = RechartsLegend
ChartLegend.displayName = "ChartLegend"

/* ── ChartLegendContent ──────────────────────────────────── */
const ChartLegendContent = React.forwardRef(
  ({ payload, className = "", hideIcon = false }, ref) => {
    const { config } = useChart()
    if (!payload || !payload.length) return null

    return (
      <div
        ref={ref}
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          gap: "20px",
          paddingTop: "12px",
          flexWrap: "wrap",
        }}
      >
        {payload.map((entry) => {
          const itemConfig = config[entry.dataKey || entry.value] || {}
          const color = entry.color || itemConfig.color || "var(--chart-1)"
          const label = itemConfig.label || entry.value

          return (
            <div
              key={entry.value}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "6px",
                fontSize: "13px",
                color: "var(--muted-foreground)",
              }}
            >
              {!hideIcon && (
                <span
                  style={{
                    display: "inline-block",
                    width: "8px",
                    height: "8px",
                    borderRadius: "50%",
                    background: color,
                  }}
                />
              )}
              {label}
            </div>
          )
        })}
      </div>
    )
  }
)
ChartLegendContent.displayName = "ChartLegendContent"

export {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
}
