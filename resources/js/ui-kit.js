/*
 * Falcon UI Kit — Entrypoint JS
 *
 * Ce fichier est le point d'entree JS pour le back-office.
 * Il charge les utilitaires du kit (theme, sidebar, toast, modal)
 * et Chart.js pour le composant <x-ui.chart>.
 *
 * Utilisez @vite(['resources/css/ui-kit.css', 'resources/js/ui-kit.js'])
 * dans votre layout back-office.
 */
import '../../vendor/falcon/ui-kit/resources/js/ui-kit.js';
import Chart from 'chart.js/auto';
window.Chart = Chart;