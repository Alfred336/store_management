import {
    Chart,
    LineController, BarController, DoughnutController,
    CategoryScale, LinearScale, ArcElement,
    PointElement, LineElement, BarElement,
    Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(
    LineController, BarController, DoughnutController,
    CategoryScale, LinearScale, ArcElement,
    PointElement, LineElement, BarElement,
    Tooltip, Legend, Filler,
);

/** Detect whether dark mode is active on the <html> element. */
function isDark() {
    return document.documentElement.classList.contains('dark');
}

/** Tailwind/Flux chart colour palette that respects dark mode. */
function chartColors(dark) {
    return {
        grid: dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)',
        tick: dark ? '#a1a1aa' : '#71717a', // zinc-400 / zinc-500
        accent: dark ? '#10b981' : '#059669', // emerald-500/600
        accentBg: dark ? 'rgba(16,185,129,.15)' : 'rgba(5,150,105,.1)',
        palette: ['#059669','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16'],
    };
}

/** Build Chart.js defaults from current theme */
function buildDefaults(type, colors) {
    const base = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: type === 'doughnut',
                position: 'bottom',
                labels: { color: colors.tick, boxWidth: 12, padding: 12 },
            },
            tooltip: { mode: 'index', intersect: false },
        },
    };

    if (type !== 'doughnut') {
        base.scales = {
            x: { grid: { color: colors.grid }, ticks: { color: colors.tick, maxRotation: 0 } },
            y: { grid: { color: colors.grid }, ticks: { color: colors.tick }, beginAtZero: true },
        };
    }

    return base;
}

/** Inject per-dataset styling if datasets lack explicit colours. */
function decorateDatasets(type, datasets, colors) {
    return datasets.map((ds, i) => {
        const c = ds.backgroundColor ?? colors.palette[i % colors.palette.length];
        if (type === 'line') {
            return {
                borderColor: ds.borderColor ?? c,
                backgroundColor: ds.backgroundColor ?? colors.accentBg,
                fill: ds.fill ?? true,
                tension: 0.35,
                pointRadius: 2,
                ...ds,
            };
        }
        if (type === 'bar') {
            return { backgroundColor: ds.backgroundColor ?? colors.palette.map((_, j) => colors.palette[j % colors.palette.length]), borderRadius: 4, ...ds };
        }
        return { backgroundColor: ds.backgroundColor ?? colors.palette, borderWidth: 0, ...ds };
    });
}

window.appChart = function (type, initialData) {
    return {
        chart: null,

        init() {
            const colors = chartColors(isDark());
            const opts   = buildDefaults(type, colors);
            const data   = {
                labels: initialData.labels ?? [],
                datasets: decorateDatasets(type, initialData.datasets ?? [], colors),
            };

            this.chart = new Chart(this.$refs.canvas, { type, data, options: opts });

            // Re-render on theme toggle (Flux toggles .dark on <html>)
            const obs = new MutationObserver(() => this.applyTheme());
            obs.observe(document.documentElement, { attributeFilter: ['class'] });
            this.$cleanup(() => { obs.disconnect(); this.chart?.destroy(); });
        },

        onUpdate(detail) {
            if (!this.chart) return;
            if (detail.type !== type) return;
            const colors = chartColors(isDark());
            this.chart.data.labels   = detail.data.labels ?? [];
            this.chart.data.datasets = decorateDatasets(type, detail.data.datasets ?? [], colors);
            this.chart.update();
        },

        applyTheme() {
            if (!this.chart) return;
            const colors = chartColors(isDark());
            const opts   = buildDefaults(type, colors);
            Object.assign(this.chart.options, opts);
            decorateDatasets(type, this.chart.data.datasets, colors).forEach((ds, i) => {
                Object.assign(this.chart.data.datasets[i], ds);
            });
            this.chart.update('none');
        },
    };
};
