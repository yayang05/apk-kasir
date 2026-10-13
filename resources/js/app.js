import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;

const openPreferencesDatabase = () => new Promise((resolve, reject) => {
	const request = window.indexedDB.open('apk-kasir-preferences', 1);
	request.onupgradeneeded = () => request.result.createObjectStore('appearance');
	request.onsuccess = () => resolve(request.result);
	request.onerror = () => reject(request.error);
});

const getStoredBackground = async () => {
	const database = await openPreferencesDatabase();

	return new Promise((resolve, reject) => {
		const transaction = database.transaction('appearance', 'readonly');
		const request = transaction.objectStore('appearance').get('background');
		request.onsuccess = () => resolve(request.result || null);
		request.onerror = () => reject(request.error);
		transaction.oncomplete = () => database.close();
		transaction.onerror = () => {
			database.close();
			reject(transaction.error);
		};
	});
};

const storeBackground = async (file) => {
	const database = await openPreferencesDatabase();

	return new Promise((resolve, reject) => {
		const transaction = database.transaction('appearance', 'readwrite');
		transaction.objectStore('appearance').put(file, 'background');
		transaction.oncomplete = () => {
			database.close();
			resolve();
		};
		transaction.onerror = () => {
			database.close();
			reject(transaction.error);
		};
	});
};

const clearStoredBackground = async () => {
	const database = await openPreferencesDatabase();

	return new Promise((resolve, reject) => {
		const transaction = database.transaction('appearance', 'readwrite');
		transaction.objectStore('appearance').delete('background');
		transaction.oncomplete = () => {
			database.close();
			resolve();
		};
		transaction.onerror = () => {
			database.close();
			reject(transaction.error);
		};
	});
};

let activeBackgroundUrl = null;

const applyGlobalBackground = (file) => {
	if (activeBackgroundUrl) URL.revokeObjectURL(activeBackgroundUrl);

	if (!file) {
		activeBackgroundUrl = null;
		document.documentElement.style.removeProperty('--user-wallpaper');
		return;
	}

	activeBackgroundUrl = URL.createObjectURL(file);
	document.documentElement.style.setProperty('--user-wallpaper', `url("${activeBackgroundUrl}")`);
};

getStoredBackground().then(applyGlobalBackground).catch(() => {});

Alpine.data('dashboardClock', () => ({
	time: '',
	timer: null,

	refresh() {
		const now = new Date();

		this.time = new Intl.DateTimeFormat('id-ID', {
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
			hour12: false,
		}).format(now);
	},

	init() {
		this.refresh();
		this.timer = window.setInterval(() => this.refresh(), 1000);
	},

	destroy() {
		window.clearInterval(this.timer);
	},
}));

Alpine.data('salesTrendChart', (points) => ({
	chart: null,

	init() {
		const currency = new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			maximumFractionDigits: 0,
		});

		this.chart = new Chart(this.$refs.canvas, {
			type: 'line',
			data: {
				labels: points.map((point) => point.label),
				datasets: [{
					label: 'Omzet',
					data: points.map((point) => point.revenue),
					fill: true,
					borderColor: '#0f766e',
					backgroundColor: 'rgba(15, 118, 110, 0.12)',
					borderWidth: 2.5,
					tension: 0.35,
					pointRadius: 3,
					pointHoverRadius: 5,
					pointBackgroundColor: '#c2410c',
					pointBorderColor: '#ffffff',
					pointBorderWidth: 2,
				}],
			},
			options: {
				maintainAspectRatio: false,
				interaction: { intersect: false, mode: 'index' },
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: (context) => `Omzet: ${currency.format(context.parsed.y)}`,
						},
					},
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { color: '#64748b' },
					},
					y: {
						beginAtZero: true,
						grid: { color: 'rgba(148, 163, 184, 0.18)' },
						ticks: {
							color: '#64748b',
							callback: (value) => currency.format(value),
						},
					},
				},
			},
		});
	},

	destroy() {
		this.chart?.destroy();
	},
}));

Alpine.data('settingsPreferences', () => ({
	theme: 'light',
	receiptWidth: '80',
	sidebarColor: '#0f172a',
	sidebarOpacity: '0.94',
	surfaceColor: '#ffffff',
	surfaceOpacity: '1',
	backgroundName: '',
	backgroundError: '',
	backgroundUrl: null,

	async init() {
		const savedTheme = localStorage.getItem('apk-kasir-theme');
		this.theme = ['light', 'dark', 'transparent'].includes(savedTheme) ? savedTheme : 'light';
		this.receiptWidth = localStorage.getItem('apk-kasir-receipt-width') === '58' ? '58' : '80';
		this.sidebarColor = localStorage.getItem('apk-kasir-sidebar-color') || '#0f172a';
		this.sidebarOpacity = localStorage.getItem('apk-kasir-sidebar-opacity') || '0.94';
		this.surfaceColor = localStorage.getItem('apk-kasir-surface-color') || '#ffffff';
		this.surfaceOpacity = localStorage.getItem('apk-kasir-surface-opacity') || '1';
		this.applySidebar();
		this.applySurface();
		this.applyTheme();
		this.applyReceiptWidth();

		try {
			const background = await getStoredBackground();

			if (background) this.applyBackground(background);
		} catch {
			this.backgroundError = 'Penyimpanan gambar lokal tidak tersedia di browser ini.';
		}
	},

	applyTheme() {
		localStorage.setItem('apk-kasir-theme', this.theme);
		document.documentElement.classList.toggle('theme-dark', this.theme === 'dark');
		document.documentElement.classList.toggle('theme-transparent', this.theme === 'transparent');
	},

	applyReceiptWidth() {
		localStorage.setItem('apk-kasir-receipt-width', this.receiptWidth);
		document.documentElement.dataset.receiptWidth = this.receiptWidth;
	},

	applySidebar() {
		localStorage.setItem('apk-kasir-sidebar-color', this.sidebarColor);
		localStorage.setItem('apk-kasir-sidebar-opacity', this.sidebarOpacity);
		const hex = this.sidebarColor.replace('#', '');
		const rgb = hex.length === 3
			? hex.split('').map((value) => parseInt(value + value, 16))
			: [parseInt(hex.slice(0, 2), 16), parseInt(hex.slice(2, 4), 16), parseInt(hex.slice(4, 6), 16)];
		document.documentElement.style.setProperty('--sidebar-bg', `rgba(${rgb.join(',')}, ${this.sidebarOpacity})`);
	},

	applySurface() {
		localStorage.setItem('apk-kasir-surface-color', this.surfaceColor);
		localStorage.setItem('apk-kasir-surface-opacity', this.surfaceOpacity);
		const hex = this.surfaceColor.replace('#', '');
		const rgb = hex.length === 3
			? hex.split('').map((value) => parseInt(value + value, 16))
			: [parseInt(hex.slice(0, 2), 16), parseInt(hex.slice(2, 4), 16), parseInt(hex.slice(4, 6), 16)];
		document.documentElement.style.setProperty('--surface-bg', `rgba(${rgb.join(',')}, ${this.surfaceOpacity})`);
	},

	applyBackground(file) {
		applyGlobalBackground(file);
		this.backgroundUrl = activeBackgroundUrl;
		this.backgroundName = file.name || 'Gambar latar';
	},

	async selectBackground(event) {
		const file = event.target.files?.[0];
		this.backgroundError = '';

		if (!file) return;
		if (!file.type.startsWith('image/')) {
			this.backgroundError = 'Pilih berkas gambar yang valid.';
			event.target.value = '';
			return;
		}
		if (file.size > 8 * 1024 * 1024) {
			this.backgroundError = 'Ukuran gambar maksimal 8 MB.';
			event.target.value = '';
			return;
		}

		try {
			await storeBackground(file);
			this.applyBackground(file);
			this.theme = 'transparent';
			this.applyTheme();
		} catch {
			this.backgroundError = 'Gambar tidak dapat disimpan pada browser ini.';
		}
	},

	async removeBackground() {
		this.backgroundError = '';

		try {
			await clearStoredBackground();
			applyGlobalBackground(null);
			this.backgroundUrl = null;
			this.backgroundName = '';
		} catch {
			this.backgroundError = 'Gambar latar tidak dapat dihapus dari browser ini.';
		}
	},

	destroy() {
		if (this.backgroundUrl) URL.revokeObjectURL(this.backgroundUrl);
	},
}));

Alpine.start();
