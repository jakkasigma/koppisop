/**
 * Custom Date Picker — pasang ke semua input[type="date"] dengan data-datepicker
 * Mendukung: single date, date-range (pair), preset shortcuts,
 *            dan date-range-trigger button (popup 2 kalender + preset)
 */
;(function () {
  'use strict'

  const MONTHS_ID = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember',
  ]
  const DAYS_SHORT = ['Min','Sen','Sel','Rab','Kam','Jum','Sab']

  function pad(n) { return String(n).padStart(2, '0') }
  function toYMD(d) { return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}` }
  function fromYMD(str) {
    if (!str) return null
    const [y, m, d] = str.split('-').map(Number)
    return new Date(y, m - 1, d)
  }
  function isSameDay(a, b) {
    return a && b
      && a.getFullYear()===b.getFullYear()
      && a.getMonth()===b.getMonth()
      && a.getDate()===b.getDate()
  }
  function startOfDay(d) { const c = new Date(d); c.setHours(0,0,0,0); return c }
  function today() { return startOfDay(new Date()) }
  function daysAgo(n) { const d = today(); d.setDate(d.getDate()-n); return d }
  function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1) }
  function endOfMonth(d)   { return new Date(d.getFullYear(), d.getMonth()+1, 0) }
  function prevMonthDate(d){ return new Date(d.getFullYear(), d.getMonth()-1, 1) }

  // ── shared overlay ─────────────────────────────────────────
  let overlay = null
  function getOverlay() {
    if (!overlay) {
      overlay = document.createElement('div')
      overlay.className = 'dp-overlay'
      document.body.appendChild(overlay)
      overlay.addEventListener('mousedown', function(e) {
        if (e.target === overlay) closeAll()
      })
    }
    return overlay
  }

  let active = null
  function closeAll() {
    document.querySelectorAll('.dp-popup.dp-open').forEach(p => p.classList.remove('dp-open'))
    document.querySelectorAll('.dp-range-popup.dp-open').forEach(p => p.classList.remove('dp-open'))
    getOverlay().classList.remove('dp-overlay-active')
    active = null
  }

  // ══════════════════════════════════════════════════════════
  // 1.  SINGLE DATE PICKER (data-datepicker on input)
  // ══════════════════════════════════════════════════════════
  function DatePicker(inputEl) {
    this.inputEl   = inputEl
    this.viewYear  = 0
    this.viewMonth = 0
    var pairId = inputEl.dataset.datepickerPair
    this.pairEl = pairId ? document.getElementById(pairId) : null
    this.role   = inputEl.dataset.datepickerRole || null
    this._build()
    this._attach()
  }

  DatePicker.prototype._build = function() {
    var pop = document.createElement('div')
    pop.className = 'dp-popup'
    pop.setAttribute('role', 'dialog')
    pop.setAttribute('aria-modal', 'true')
    pop.setAttribute('aria-label', 'Pilih tanggal')
    pop.innerHTML =
      '<div class="dp-presets"></div>' +
      '<div class="dp-cal">' +
        '<div class="dp-nav">' +
          '<button type="button" class="dp-nav-btn dp-prev" aria-label="Bulan sebelumnya">&#8249;</button>' +
          '<div class="dp-nav-mid">' +
            '<select class="dp-sel-month" aria-label="Pilih bulan"></select>' +
            '<select class="dp-sel-year"  aria-label="Pilih tahun"></select>' +
          '</div>' +
          '<button type="button" class="dp-nav-btn dp-next" aria-label="Bulan berikutnya">&#8250;</button>' +
        '</div>' +
        '<div class="dp-grid">' +
          DAYS_SHORT.map(d => '<div class="dp-day-head">'+d+'</div>').join('') +
        '</div>' +
      '</div>' +
      '<div class="dp-footer">' +
        '<button type="button" class="dp-btn-clear">Hapus</button>' +
        '<button type="button" class="dp-btn-today">Hari ini</button>' +
        '<button type="button" class="dp-btn-apply">Terapkan</button>' +
      '</div>'

    this.popup    = pop
    this.grid     = pop.querySelector('.dp-grid')
    this.selMonth = pop.querySelector('.dp-sel-month')
    this.selYear  = pop.querySelector('.dp-sel-year')
    this.presetsEl= pop.querySelector('.dp-presets')

    this._fillMonthSelect()
    this._fillYearSelect()
    this._buildPresets()
    document.body.appendChild(pop)
  }

  DatePicker.prototype._fillMonthSelect = function() {
    this.selMonth.innerHTML = MONTHS_ID.map((m,i) => '<option value="'+i+'">'+m+'</option>').join('')
  }

  DatePicker.prototype._fillYearSelect = function() {
    const cur = new Date().getFullYear()
    let html = ''
    for (let y = cur+2; y >= cur-10; y--) html += '<option value="'+y+'">'+y+'</option>'
    this.selYear.innerHTML = html
  }

  DatePicker.prototype._buildPresets = function() {
    const t = today()
    const presets = [
      { label: 'Hari ini',   start: t,                   end: t },
      { label: 'Kemarin',    start: daysAgo(1),           end: daysAgo(1) },
      { label: '7 hari',     start: daysAgo(6),           end: t },
      { label: '30 hari',    start: daysAgo(29),          end: t },
      { label: '90 hari',    start: daysAgo(89),          end: t },
      { label: 'Bulan ini',  start: startOfMonth(t),      end: endOfMonth(t) },
      { label: 'Bulan lalu', start: startOfMonth(prevMonthDate(t)), end: endOfMonth(prevMonthDate(t)) },
    ]
    this.presetsEl.innerHTML = presets.map((p,i) =>
      '<button type="button" class="dp-preset" data-idx="'+i+'">'+p.label+'</button>'
    ).join('')
    this._presets = presets
    this.presetsEl.addEventListener('click', e => {
      const btn = e.target.closest('.dp-preset')
      if (!btn) return
      this._applyPreset(this._presets[+btn.dataset.idx])
    })
  }

  DatePicker.prototype._applyPreset = function(p) {
    if (this.pairEl) {
      if (this.role === 'start') {
        this.inputEl.value = toYMD(p.start)
        this.pairEl.value  = toYMD(p.end)
        this._triggerChange(this.pairEl)
      } else if (this.role === 'end') {
        this.pairEl.value  = toYMD(p.start)
        this.inputEl.value = toYMD(p.end)
        this._triggerChange(this.pairEl)
      } else {
        this.inputEl.value = toYMD(p.start)
      }
      this._triggerChange(this.inputEl)
    } else {
      this.inputEl.value = toYMD(p.start)
      this._triggerChange(this.inputEl)
    }
    closeAll()
    this._maybeSubmit()
  }

  DatePicker.prototype._attach = function() {
    this.inputEl.addEventListener('click', e => {
      e.stopPropagation()
      if (active && active.inputEl === this.inputEl) { closeAll(); return }
      closeAll()
      this._open()
    })
    this.inputEl.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this._open() }
      if (e.key === 'Escape') closeAll()
    })
    this.popup.querySelector('.dp-prev').addEventListener('click', () => {
      if (this.viewMonth === 0) { this.viewMonth = 11; this.viewYear-- }
      else this.viewMonth--
      this._syncSelects(); this._renderDays()
    })
    this.popup.querySelector('.dp-next').addEventListener('click', () => {
      if (this.viewMonth === 11) { this.viewMonth = 0; this.viewYear++ }
      else this.viewMonth++
      this._syncSelects(); this._renderDays()
    })
    this.selMonth.addEventListener('change', () => { this.viewMonth = +this.selMonth.value; this._renderDays() })
    this.selYear.addEventListener('change',  () => { this.viewYear  = +this.selYear.value;  this._renderDays() })
    this.popup.querySelector('.dp-btn-clear').addEventListener('click', () => {
      this.inputEl.value = ''; this._triggerChange(this.inputEl); closeAll(); this._maybeSubmit()
    })
    this.popup.querySelector('.dp-btn-today').addEventListener('click', () => {
      this.inputEl.value = toYMD(today()); this._triggerChange(this.inputEl); closeAll(); this._maybeSubmit()
    })
    this.popup.querySelector('.dp-btn-apply').addEventListener('click', () => {
      closeAll(); this._maybeSubmit()
    })
    this.popup.addEventListener('mousedown', e => e.stopPropagation())
  }

  DatePicker.prototype._open = function() {
    const val = fromYMD(this.inputEl.value) || today()
    this.viewYear  = val.getFullYear()
    this.viewMonth = val.getMonth()
    this._syncSelects()
    this._renderDays()
    this._position()
    this.popup.classList.add('dp-open')
    getOverlay().classList.add('dp-overlay-active')
    active = { picker: this, inputEl: this.inputEl }
  }

  DatePicker.prototype._syncSelects = function() {
    this.selMonth.value = this.viewMonth
    this.selYear.value  = this.viewYear
  }

  DatePicker.prototype._renderDays = function() {
    this.grid.querySelectorAll('.dp-cell').forEach(c => c.remove())
    const selected   = fromYMD(this.inputEl.value)
    const pairVal    = this.pairEl ? fromYMD(this.pairEl.value) : null
    let rangeStart = null, rangeEnd = null
    if (this.pairEl) {
      rangeStart = this.role === 'start' ? selected : pairVal
      rangeEnd   = this.role === 'start' ? pairVal  : selected
      if (rangeStart && rangeEnd && rangeStart > rangeEnd)
        [rangeStart, rangeEnd] = [rangeEnd, rangeStart]
    }
    const firstDay  = new Date(this.viewYear, this.viewMonth, 1)
    const lastDay   = new Date(this.viewYear, this.viewMonth+1, 0)
    const startDow  = firstDay.getDay()
    const todayDate = today()
    const frag = document.createDocumentFragment()
    for (let i = 0; i < startDow; i++) {
      const e = document.createElement('div'); e.className = 'dp-cell dp-cell-empty'; frag.appendChild(e)
    }
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const date = new Date(this.viewYear, this.viewMonth, d)
      const cell = document.createElement('div')
      cell.className = 'dp-cell'; cell.textContent = d; cell.dataset.date = toYMD(date)
      if (isSameDay(date, todayDate)) cell.classList.add('dp-today')
      if (isSameDay(date, selected))  cell.classList.add('dp-selected')
      if (isSameDay(date, pairVal))   cell.classList.add('dp-selected','dp-selected-pair')
      if (rangeStart && rangeEnd) {
        if (date > rangeStart && date < rangeEnd) cell.classList.add('dp-in-range')
        if (isSameDay(date, rangeStart))          cell.classList.add('dp-range-start')
        if (isSameDay(date, rangeEnd))            cell.classList.add('dp-range-end')
      }
      cell.addEventListener('click', () => this._selectDate(date))
      frag.appendChild(cell)
    }
    this.grid.appendChild(frag)
  }

  DatePicker.prototype._selectDate = function(date) {
    this.inputEl.value = toYMD(date)
    this._triggerChange(this.inputEl)
    closeAll()
    this._maybeSubmit()
  }

  DatePicker.prototype._triggerChange = function(el) {
    el.dispatchEvent(new Event('change', { bubbles: true }))
    el.dispatchEvent(new Event('input',  { bubbles: true }))
  }

  DatePicker.prototype._maybeSubmit = function() {
    if (this.inputEl.dataset.datepickerAutosubmit === 'true') {
      const form = this.inputEl.closest('form')
      if (form) form.submit()
    }
  }

  DatePicker.prototype._position = function() {
    const rect  = this.inputEl.getBoundingClientRect()
    const scrollY = window.scrollY, scrollX = window.scrollX
    const popW = 280, popH = 380
    let top  = rect.bottom + scrollY + 6
    let left = rect.left   + scrollX
    if (rect.bottom + popH + 6 > window.innerHeight) top = rect.top + scrollY - popH - 6
    if (left + popW > window.innerWidth + scrollX) left = window.innerWidth + scrollX - popW - 8
    if (left < 8) left = 8
    this.popup.style.top  = top  + 'px'
    this.popup.style.left = left + 'px'
  }

  // ══════════════════════════════════════════════════════════
  // 2.  DATE RANGE TRIGGER (data-daterange-trigger button)
  //     Opens a combined popup with presets + two month pickers
  //     Attr: data-start="#id_start_input" data-end="#id_end_input"
  //           data-autosubmit="true"
  // ══════════════════════════════════════════════════════════
  const RANGE_PRESETS = [
    { label: 'Hari ini',    fn: () => ({ start: today(),                 end: today() }) },
    { label: 'Kemarin',     fn: () => ({ start: daysAgo(1),              end: daysAgo(1) }) },
    { label: '7 hari',      fn: () => ({ start: daysAgo(6),              end: today() }) },
    { label: '30 hari',     fn: () => ({ start: daysAgo(29),             end: today() }) },
    { label: '90 hari',     fn: () => ({ start: daysAgo(89),             end: today() }) },
    { label: 'Bulan ini',   fn: () => ({ start: startOfMonth(today()),   end: endOfMonth(today()) }) },
    { label: 'Bulan lalu',  fn: () => ({ start: startOfMonth(prevMonthDate(today())), end: endOfMonth(prevMonthDate(today())) }) },
    { label: 'Tahun ini',   fn: () => ({ start: new Date(today().getFullYear(), 0, 1), end: new Date(today().getFullYear(), 11, 31) }) },
  ]

  function RangeTrigger(btn) {
    this.btn       = btn
    this.startEl   = document.querySelector(btn.dataset.start)
    this.endEl     = document.querySelector(btn.dataset.end)
    this.autoSubmit = btn.dataset.autosubmit === 'true'
    if (!this.startEl || !this.endEl) return
    this._tempStart = fromYMD(this.startEl.value)
    this._tempEnd   = fromYMD(this.endEl.value)
    this._selecting = null   // 'start' | 'end' | null
    this._build()
    this._attach()
    this._updateBtnLabel()
  }

  RangeTrigger.prototype._build = function() {
    const pop = document.createElement('div')
    pop.className = 'dp-range-popup'
    pop.setAttribute('role', 'dialog')
    pop.setAttribute('aria-modal', 'true')
    pop.setAttribute('aria-label', 'Pilih rentang tanggal')

    pop.innerHTML = `
      <div class="dp-range-header">
        <span class="dp-range-title">Pilih Rentang Tanggal</span>
        <button type="button" class="dp-range-close" aria-label="Tutup">&#10005;</button>
      </div>
      <div class="dp-range-presets">
        ${RANGE_PRESETS.map((p,i) => `<button type="button" class="dp-preset" data-idx="${i}">${p.label}</button>`).join('')}
      </div>
      <div class="dp-range-inputs">
        <div class="dp-range-field">
          <label class="dp-range-label">Tanggal Awal</label>
          <div class="dp-range-input-wrap">
            <input type="text" class="dp-range-input dp-range-start-display" readonly placeholder="Pilih tanggal..." aria-label="Tanggal awal">
            <span class="dp-range-icon">&#128197;</span>
          </div>
        </div>
        <div class="dp-range-arrow">&#8594;</div>
        <div class="dp-range-field">
          <label class="dp-range-label">Tanggal Akhir</label>
          <div class="dp-range-input-wrap">
            <input type="text" class="dp-range-input dp-range-end-display" readonly placeholder="Pilih tanggal..." aria-label="Tanggal akhir">
            <span class="dp-range-icon">&#128197;</span>
          </div>
        </div>
      </div>
      <div class="dp-range-cals">
        <div class="dp-range-cal dp-range-cal-start">
          <div class="dp-nav">
            <button type="button" class="dp-nav-btn dp-range-prev" aria-label="Bulan sebelumnya">&#8249;</button>
            <div class="dp-nav-mid">
              <select class="dp-sel-month dp-range-month-start" aria-label="Pilih bulan"></select>
              <select class="dp-sel-year  dp-range-year-start"  aria-label="Pilih tahun"></select>
            </div>
            <button type="button" class="dp-nav-btn dp-range-next-start" aria-label="Bulan berikutnya">&#8250;</button>
          </div>
          <div class="dp-grid">
            ${DAYS_SHORT.map(d => '<div class="dp-day-head">'+d+'</div>').join('')}
          </div>
        </div>
        <div class="dp-range-cal dp-range-cal-end">
          <div class="dp-nav">
            <button type="button" class="dp-nav-btn dp-range-prev-end" aria-label="Bulan sebelumnya">&#8249;</button>
            <div class="dp-nav-mid">
              <select class="dp-sel-month dp-range-month-end" aria-label="Pilih bulan"></select>
              <select class="dp-sel-year  dp-range-year-end"  aria-label="Pilih tahun"></select>
            </div>
            <button type="button" class="dp-nav-btn dp-range-next-end" aria-label="Bulan berikutnya">&#8250;</button>
          </div>
          <div class="dp-grid">
            ${DAYS_SHORT.map(d => '<div class="dp-day-head">'+d+'</div>').join('')}
          </div>
        </div>
      </div>
      <div class="dp-range-footer">
        <button type="button" class="dp-range-btn-reset">Reset</button>
        <button type="button" class="dp-range-btn-apply dp-btn-apply">Terapkan</button>
      </div>`

    this.pop         = pop
    this.startDisplay = pop.querySelector('.dp-range-start-display')
    this.endDisplay   = pop.querySelector('.dp-range-end-display')
    this.gridStart    = pop.querySelector('.dp-range-cal-start .dp-grid')
    this.gridEnd      = pop.querySelector('.dp-range-cal-end  .dp-grid')
    this.monthStart   = pop.querySelector('.dp-range-month-start')
    this.yearStart    = pop.querySelector('.dp-range-year-start')
    this.monthEnd     = pop.querySelector('.dp-range-month-end')
    this.yearEnd      = pop.querySelector('.dp-range-year-end')

    this._fillSelect(this.monthStart); this._fillSelect(this.monthEnd)
    this._fillYearSelect(this.yearStart); this._fillYearSelect(this.yearEnd)

    // Set initial view months
    const anchor = fromYMD(this.startEl.value) || today()
    this.viewStartYear  = anchor.getFullYear()
    this.viewStartMonth = anchor.getMonth()
    // End calendar shows next month by default if same as start
    const anchorEnd = fromYMD(this.endEl.value) || anchor
    this.viewEndYear  = anchorEnd.getFullYear()
    this.viewEndMonth = anchorEnd.getMonth()
    if (this.viewEndYear === this.viewStartYear && this.viewEndMonth === this.viewStartMonth) {
      this.viewEndMonth++
      if (this.viewEndMonth > 11) { this.viewEndMonth = 0; this.viewEndYear++ }
    }

    document.body.appendChild(pop)
    this._syncSelects()
    this._renderBoth()
  }

  RangeTrigger.prototype._fillSelect = function(sel) {
    sel.innerHTML = MONTHS_ID.map((m,i) => `<option value="${i}">${m}</option>`).join('')
  }

  RangeTrigger.prototype._fillYearSelect = function(sel) {
    const cur = new Date().getFullYear()
    let html = ''
    for (let y = cur+2; y >= cur-10; y--) html += `<option value="${y}">${y}</option>`
    sel.innerHTML = html
  }

  RangeTrigger.prototype._syncSelects = function() {
    this.monthStart.value = this.viewStartMonth
    this.yearStart.value  = this.viewStartYear
    this.monthEnd.value   = this.viewEndMonth
    this.yearEnd.value    = this.viewEndYear
  }

  RangeTrigger.prototype._attach = function() {
    // Trigger button
    this.btn.addEventListener('click', e => {
      e.stopPropagation()
      if (this.pop.classList.contains('dp-open')) { closeAll(); return }
      closeAll()
      this._tempStart = fromYMD(this.startEl.value)
      this._tempEnd   = fromYMD(this.endEl.value)
      this._selecting = 'start'
      this._refreshDisplays()
      this._syncSelects()
      this._renderBoth()
      this._position()
      this.pop.classList.add('dp-open')
      getOverlay().classList.add('dp-overlay-active')
      active = { pop: this.pop }
    })
    this.pop.addEventListener('mousedown', e => e.stopPropagation())

    // Close
    this.pop.querySelector('.dp-range-close').addEventListener('click', () => closeAll())

    // Presets
    this.pop.querySelector('.dp-range-presets').addEventListener('click', e => {
      const btn = e.target.closest('.dp-preset')
      if (!btn) return
      const p = RANGE_PRESETS[+btn.dataset.idx].fn()
      this._tempStart = p.start; this._tempEnd = p.end
      this._selecting = null
      this._refreshDisplays()
      this._renderBoth()
    })

    // Start field click → select start
    this.startDisplay.addEventListener('click', () => { this._selecting = 'start'; this._refreshDisplays() })
    this.endDisplay.addEventListener('click',   () => { this._selecting = 'end';   this._refreshDisplays() })

    // Nav — start cal
    this.pop.querySelector('.dp-range-prev').addEventListener('click', () => {
      if (this.viewStartMonth === 0) { this.viewStartMonth = 11; this.viewStartYear-- }
      else this.viewStartMonth--
      this._syncSelects(); this._renderBoth()
    })
    this.pop.querySelector('.dp-range-next-start').addEventListener('click', () => {
      if (this.viewStartMonth === 11) { this.viewStartMonth = 0; this.viewStartYear++ }
      else this.viewStartMonth++
      this._syncSelects(); this._renderBoth()
    })
    this.monthStart.addEventListener('change', () => { this.viewStartMonth = +this.monthStart.value; this._renderBoth() })
    this.yearStart.addEventListener('change',  () => { this.viewStartYear  = +this.yearStart.value;  this._renderBoth() })

    // Nav — end cal
    this.pop.querySelector('.dp-range-prev-end').addEventListener('click', () => {
      if (this.viewEndMonth === 0) { this.viewEndMonth = 11; this.viewEndYear-- }
      else this.viewEndMonth--
      this._syncSelects(); this._renderBoth()
    })
    this.pop.querySelector('.dp-range-next-end').addEventListener('click', () => {
      if (this.viewEndMonth === 11) { this.viewEndMonth = 0; this.viewEndYear++ }
      else this.viewEndMonth++
      this._syncSelects(); this._renderBoth()
    })
    this.monthEnd.addEventListener('change', () => { this.viewEndMonth = +this.monthEnd.value; this._renderBoth() })
    this.yearEnd.addEventListener('change',  () => { this.viewEndYear  = +this.yearEnd.value;  this._renderBoth() })

    // Footer
    this.pop.querySelector('.dp-range-btn-reset').addEventListener('click', () => {
      this._tempStart = null; this._tempEnd = null; this._selecting = 'start'
      this._refreshDisplays(); this._renderBoth()
    })
    this.pop.querySelector('.dp-range-btn-apply').addEventListener('click', () => {
      this._apply()
    })
  }

  RangeTrigger.prototype._renderBoth = function() {
    this._renderGrid(this.gridStart, this.viewStartYear, this.viewStartMonth)
    this._renderGrid(this.gridEnd,   this.viewEndYear,   this.viewEndMonth)
  }

  RangeTrigger.prototype._renderGrid = function(grid, year, month) {
    grid.querySelectorAll('.dp-cell').forEach(c => c.remove())
    const s = this._tempStart, e = this._tempEnd
    let rangeStart = s, rangeEnd = e
    if (rangeStart && rangeEnd && rangeStart > rangeEnd) [rangeStart, rangeEnd] = [rangeEnd, rangeStart]
    const firstDay = new Date(year, month, 1)
    const lastDay  = new Date(year, month+1, 0)
    const startDow = firstDay.getDay()
    const todayDate = today()
    const frag = document.createDocumentFragment()
    for (let i = 0; i < startDow; i++) {
      const em = document.createElement('div'); em.className = 'dp-cell dp-cell-empty'; frag.appendChild(em)
    }
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const date = new Date(year, month, d)
      const cell = document.createElement('div')
      cell.className = 'dp-cell'; cell.textContent = d; cell.dataset.date = toYMD(date)
      if (isSameDay(date, todayDate)) cell.classList.add('dp-today')
      if (isSameDay(date, s))  cell.classList.add('dp-range-start','dp-selected')
      if (isSameDay(date, e))  cell.classList.add('dp-range-end','dp-selected')
      if (isSameDay(date, s) && isSameDay(date, e)) {
        cell.classList.remove('dp-range-start','dp-range-end')
        cell.classList.add('dp-selected')
      }
      if (rangeStart && rangeEnd && date > rangeStart && date < rangeEnd) cell.classList.add('dp-in-range')
      cell.addEventListener('click', () => this._selectDate(date))
      frag.appendChild(cell)
    }
    grid.appendChild(frag)
  }

  RangeTrigger.prototype._selectDate = function(date) {
    if (this._selecting === 'start' || !this._tempStart) {
      this._tempStart = date
      this._selecting = 'end'
      // If new start > old end, reset end
      if (this._tempEnd && date > this._tempEnd) this._tempEnd = null
    } else if (this._selecting === 'end') {
      if (date < this._tempStart) {
        this._tempEnd   = this._tempStart
        this._tempStart = date
      } else {
        this._tempEnd = date
      }
      this._selecting = null
    } else {
      // both set, restart selection
      this._tempStart = date; this._tempEnd = null; this._selecting = 'end'
    }
    this._refreshDisplays()
    this._renderBoth()
  }

  RangeTrigger.prototype._refreshDisplays = function() {
    const fmtDisp = d => d
      ? d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
      : ''
    this.startDisplay.value = fmtDisp(this._tempStart)
    this.endDisplay.value   = fmtDisp(this._tempEnd)
    // Highlight active field
    this.startDisplay.closest('.dp-range-field').classList.toggle('dp-range-field-active', this._selecting === 'start')
    this.endDisplay.closest('.dp-range-field').classList.toggle('dp-range-field-active',   this._selecting === 'end')
  }

  RangeTrigger.prototype._apply = function() {
    if (this._tempStart) {
      this.startEl.value = toYMD(this._tempStart)
      this.startEl.dispatchEvent(new Event('change', { bubbles: true }))
    }
    if (this._tempEnd) {
      this.endEl.value = toYMD(this._tempEnd)
      this.endEl.dispatchEvent(new Event('change', { bubbles: true }))
    }
    this._updateBtnLabel()
    closeAll()
    if (this.autoSubmit) {
      const form = this.startEl.closest('form')
      if (form) form.submit()
    }
  }

  RangeTrigger.prototype._updateBtnLabel = function() {
    const s = fromYMD(this.startEl.value)
    const e = fromYMD(this.endEl.value)
    const fmt = d => d ? d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
    if (s || e) {
      this.btn.innerHTML = `<span class="dp-trigger-icon">&#128197;</span> <span class="dp-trigger-range">${fmt(s)} &ndash; ${fmt(e)}</span>`
    } else {
      this.btn.innerHTML = `<span class="dp-trigger-icon">&#128197;</span> <span class="dp-trigger-label">Pilih Periode</span>`
    }
  }

  RangeTrigger.prototype._position = function() {
    const rect  = this.btn.getBoundingClientRect()
    const scrollY = window.scrollY, scrollX = window.scrollX
    const popW = this.pop.offsetWidth || 640
    const popH = this.pop.offsetHeight || 420
    let top  = rect.bottom + scrollY + 6
    let left = rect.left   + scrollX
    if (rect.bottom + popH + 6 > window.innerHeight) top = rect.top + scrollY - popH - 6
    if (left + popW > window.innerWidth + scrollX) left = window.innerWidth + scrollX - popW - 8
    if (left < 8) left = 8
    this.pop.style.top  = top  + 'px'
    this.pop.style.left = left + 'px'
  }

  // ── init ───────────────────────────────────────────────────
  function init() {
    // Single date pickers
    document.querySelectorAll('[data-datepicker]').forEach(el => {
      el.setAttribute('readonly', 'readonly')
      el.style.cursor = 'pointer'
      new DatePicker(el)
    })
    // Range trigger buttons
    document.querySelectorAll('[data-daterange-trigger]').forEach(btn => {
      new RangeTrigger(btn)
    })
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll() })
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }
})()
