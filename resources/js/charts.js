// Admin charts only; loaded on the dashboards, not on member pages.
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, BarController, BarElement, DoughnutController, ArcElement, Legend } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, BarController, BarElement, DoughnutController, ArcElement, Legend);
Chart.defaults.font.family = 'DM Sans, ui-sans-serif, system-ui, sans-serif';
Chart.defaults.color = '#52615a';

const PALETTE = ['#109648', '#f2b705', '#0b2a17', '#7cc79a', '#c98f00', '#4a6b57'];

document.querySelectorAll('[data-chart]').forEach((el) => {
    const { type, labels, values, label } = JSON.parse(el.dataset.chart);
    const canvas = el.querySelector('canvas');
    const line = type === 'line';
    new Chart(canvas, {
        type,
        data: {
            labels,
            datasets: [{
                label: label || 'Members',
                data: values,
                borderColor: line ? '#109648' : '#fff',
                backgroundColor: line ? 'rgba(16,150,72,0.12)' : (type === 'bar' ? '#109648' : PALETTE),
                fill: line,
                tension: 0.3,
                pointRadius: 0,
                borderWidth: line ? 2 : 1,
                borderRadius: type === 'bar' ? 6 : 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
            scales: type === 'doughnut' ? {} : {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e8eee9' } },
            },
        },
    });
});
