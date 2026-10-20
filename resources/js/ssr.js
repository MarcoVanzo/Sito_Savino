/**
 * Il server SSR di Inertia (`php artisan inertia:start-ssr`, porta 13714).
 * Il rendering sta in paginaSsr.js, che i test usano senza aprire la porta.
 */
import process from 'node:process';
import createServer from '@inertiajs/vue3/server';
import { rendiLaPagina } from './paginaSsr.js';

createServer(rendiLaPagina, { port: Number(process.env.INERTIA_SSR_PORT ?? 13714) });
