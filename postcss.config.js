import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

// Tailwind 3 per il sito pubblico. Il tema del pannello Filament e' gia'
// compilato da Tailwind 4 (@tailwindcss/vite, vedi vite.config.js) e qui si
// salta: Tailwind 3 rifiuterebbe i suoi `@layer` senza `@tailwind`.
const tailwind3TranneIlPannello = {
    postcssPlugin: 'tailwind3-tranne-il-pannello',
    async Once(root, { result }) {
        const file = root.source?.input.file ?? '';

        if (file.includes('/resources/css/filament/')) {
            return;
        }

        const compilato = await postcss([tailwindcss()]).process(root, { ...result.opts, from: file || undefined });

        // `process()` di un Root lavora sullo stesso oggetto: si ricopia solo
        // se ne ha prodotto un altro.
        if (compilato.root !== root) {
            root.removeAll();
            root.append(compilato.root.nodes);
        }

        result.messages.push(...compilato.messages);
    },
};

export default {
    plugins: [tailwind3TranneIlPannello, autoprefixer()],
};
