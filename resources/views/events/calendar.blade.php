@extends('layouts.app')

@section('title', 'Calendario Eventi | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="section-heading mb-4">
            <span class="eyebrow">Tutti gli eventi</span>
            <h1 class="display-6 fw-bold mb-1">Calendario</h1>
            <p class="text-muted mb-0">Visualizza gli eventi programmati per mese o settimana.</p>
        </div>

        <div class="card border-0 shadow-soft" id="calendar-app" data-calendar-url="/api/v1/events/calendar/">
            <div class="card-body p-4">
                <div class="calendar-toolbar d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-dark btn-sm" id="cal-prev" aria-label="Periodo precedente">&lsaquo;</button>
                        <h2 class="h5 mb-0" id="cal-title">-</h2>
                        <button class="btn btn-outline-dark btn-sm" id="cal-next" aria-label="Periodo successivo">&rsaquo;</button>
                    </div>
                    <div class="btn-group" role="group" aria-label="Vista calendario">
                        <button class="btn btn-outline-dark btn-sm active" id="cal-view-month" data-cal-view="month">Mese</button>
                        <button class="btn btn-outline-dark btn-sm" id="cal-view-week" data-cal-view="week">Settimana</button>
                    </div>
                </div>

                <div class="calendar-legend d-flex flex-wrap gap-3 mb-3 small">
                    <span><span class="calendar-legend-dot" style="background:#6366f1"></span> Culturale</span>
                    <span><span class="calendar-legend-dot" style="background:#0ea5e9"></span> Sociale</span>
                    <span><span class="calendar-legend-dot" style="background:#f59e0b"></span> Beneficenza</span>
                    <span><span class="calendar-legend-dot" style="background:#22c55e"></span> Sportivo</span>
                    <span><span class="calendar-legend-dot" style="background:#8b5cf6"></span> Formativo</span>
                </div>

                <div class="calendar-weekday-header" id="cal-weekday-header"></div>
                <div class="calendar-grid" id="cal-grid"><div class="text-center py-5 text-muted">Caricamento calendario...</div></div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    var WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    var MONTHS = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    var app = document.getElementById('calendar-app');
    if (!app) return;
    var apiUrl = app.dataset.calendarUrl;
    var grid = document.getElementById('cal-grid');
    var titleEl = document.getElementById('cal-title');
    var weekdayHeader = document.getElementById('cal-weekday-header');
    var viewMode = 'month';
    var currentDate = new Date();

    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function isSameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }
    function renderWeekdayHeader() {
        weekdayHeader.innerHTML = '';
        weekdayHeader.className = 'calendar-weekday-header';
        WEEKDAYS.forEach(function (day) {
            var cell = document.createElement('div');
            cell.className = 'calendar-weekday-cell';
            cell.textContent = day;
            weekdayHeader.appendChild(cell);
        });
    }
    function buildMonthCells(year, month) {
        var first = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0).getDate();
        var startWeekday = (first.getDay() + 6) % 7;
        var cells = [];
        for (var i = 0; i < startWeekday; i++) cells.push({ date: null, label: '', isOther: true });
        for (var d = 1; d <= lastDay; d++) cells.push({ date: new Date(year, month, d), label: d, isOther: false });
        var remaining = 7 - (cells.length % 7);
        if (remaining < 7) for (var j = 0; j < remaining; j++) cells.push({ date: null, label: '', isOther: true });
        return cells;
    }
    function buildWeekCells(baseDate) {
        var monday = new Date(baseDate);
        monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
        var cells = [];
        for (var i = 0; i < 7; i++) {
            var d = new Date(monday);
            d.setDate(d.getDate() + i);
            cells.push({ date: d, label: d.getDate(), isOther: false });
        }
        return cells;
    }
    function renderGrid(cells, events) {
        grid.innerHTML = '';
        grid.className = 'calendar-grid' + (viewMode === 'week' ? ' calendar-grid-week' : '');
        var today = new Date();
        cells.forEach(function (cell) {
            var el = document.createElement('div');
            el.className = 'calendar-cell';
            if (cell.isOther) el.classList.add('calendar-cell-other');
            if (cell.date && isSameDay(cell.date, today)) el.classList.add('calendar-cell-today');
            var header = document.createElement('div');
            header.className = 'calendar-cell-header';
            header.textContent = cell.label;
            el.appendChild(header);
            if (cell.date) {
                var dayStr = formatDate(cell.date);
                events.filter(function (ev) {
                    return dayStr >= ev.start.substring(0, 10) && dayStr <= ev.end.substring(0, 10);
                }).forEach(function (ev) {
                    var badge = document.createElement('a');
                    badge.href = ev.url;
                    badge.className = 'calendar-event-badge';
                    badge.style.setProperty('--event-color', ev.color);
                    badge.textContent = ev.title;
                    badge.title = ev.title;
                    el.appendChild(badge);
                });
            }
            grid.appendChild(el);
        });
    }
    function updateTitle() {
        if (viewMode === 'month') {
            titleEl.textContent = MONTHS[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
        } else {
            var monday = new Date(currentDate);
            monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
            var sunday = new Date(monday);
            sunday.setDate(sunday.getDate() + 6);
            titleEl.textContent = monday.getDate() + '/' + (monday.getMonth() + 1) + ' - ' + sunday.getDate() + '/' + (sunday.getMonth() + 1) + ' ' + currentDate.getFullYear();
        }
    }
    function loadCalendar() {
        updateTitle();
        renderWeekdayHeader();
        fetch(apiUrl + '?view=' + viewMode + '&date=' + formatDate(currentDate))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var events = data.data && data.data.items ? data.data.items : [];
                renderGrid(viewMode === 'month' ? buildMonthCells(currentDate.getFullYear(), currentDate.getMonth()) : buildWeekCells(currentDate), events);
            })
            .catch(function () {
                grid.innerHTML = '<div class="text-center py-5 text-muted">Errore nel caricamento del calendario.</div>';
            });
    }
    document.getElementById('cal-prev').addEventListener('click', function () {
        viewMode === 'month' ? currentDate.setMonth(currentDate.getMonth() - 1) : currentDate.setDate(currentDate.getDate() - 7);
        loadCalendar();
    });
    document.getElementById('cal-next').addEventListener('click', function () {
        viewMode === 'month' ? currentDate.setMonth(currentDate.getMonth() + 1) : currentDate.setDate(currentDate.getDate() + 7);
        loadCalendar();
    });
    document.querySelectorAll('[data-cal-view]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            viewMode = this.dataset.calView;
            document.querySelectorAll('[data-cal-view]').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');
            loadCalendar();
        });
    });
    loadCalendar();
})();
</script>
@endpush
