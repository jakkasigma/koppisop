import React from "react"
import { createRoot } from "react-dom/client"
import { StatistikChart } from "./components/StatistikChart"

function mountStatistikChart() {
  const el = document.getElementById("react-statistik-chart")
  if (!el) {
    console.warn("[StatistikChart] #react-statistik-chart not found in DOM")
    return
  }

  let dailyData, monthlyData, methodData, methodKeys, methodLabels, methodColors
  try {
    dailyData    = JSON.parse(el.dataset.daily        || "[]")
    monthlyData  = JSON.parse(el.dataset.monthly      || "[]")
    methodData   = JSON.parse(el.dataset.methodData   || "[]")
    methodKeys   = JSON.parse(el.dataset.methodKeys   || "[]")
    methodLabels = JSON.parse(el.dataset.methodLabels || "{}")
    methodColors = JSON.parse(el.dataset.methodColors || "{}")
  } catch (e) {
    console.error("[StatistikChart] JSON parse error:", e)
    el.innerHTML = '<div style="padding:20px;color:red">Error loading chart data: ' + e.message + "</div>"
    return
  }

  const tanggalAwal  = el.dataset.awal  || ""
  const tanggalAkhir = el.dataset.akhir || ""

  console.log("[StatistikChart] mounting — daily:", dailyData.length, "monthly:", monthlyData.length, "method:", methodData.length)

  try {
    createRoot(el).render(
      <StatistikChart
        dailyData={dailyData}
        monthlyData={monthlyData}
        methodData={methodData}
        methodKeys={methodKeys}
        methodLabels={methodLabels}
        methodColors={methodColors}
        tanggalAwal={tanggalAwal}
        tanggalAkhir={tanggalAkhir}
      />
    )
  } catch (e) {
    console.error("[StatistikChart] render error:", e)
    el.innerHTML = '<div style="padding:20px;color:red">Chart render error: ' + e.message + "</div>"
  }
}

// Vite output is type="module" — always deferred, DOM already ready when this runs
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mountStatistikChart)
} else {
  mountStatistikChart()
}
