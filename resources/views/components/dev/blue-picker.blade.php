{{--
    OUTIL PROVISOIRE (choix du bleu par la cliente). Composant 100% autonome (HTML + style + JS).
    Pour tout retirer : supprimer ce fichier + la ligne <x-dev.blue-picker /> dans layouts/web.blade.php.
--}}
<script>
    // 1) Applique tout de suite la couleur memorisee (evite le flash au chargement).
    (function () {
        try {
            var saved = localStorage.getItem('festilaw-blue');
            if (saved) { document.documentElement.style.setProperty('--color-blue', saved); }
        } catch (e) {}
    })();

    // 2) Logique du widget (Alpine).
    document.addEventListener('alpine:init', () => {
        Alpine.data('bluePicker', () => ({
            defaultColor: '#0F1199',
            color: '#0F1199',
            open: true,
            copied: false,
            init() {
                try {
                    const saved = localStorage.getItem('festilaw-blue');
                    if (saved) { this.color = saved; }
                } catch (e) {}
                this.apply();
            },
            apply() {
                document.documentElement.style.setProperty('--color-blue', this.color);
                try { localStorage.setItem('festilaw-blue', this.color); } catch (e) {}
            },
            reset() {
                this.color = this.defaultColor;
                this.apply();
            },
            copy() {
                try { navigator.clipboard.writeText(this.color); } catch (e) {}
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1200);
            },
        }));
    });
</script>

<style>
    .bluepick { position: fixed; bottom: 20px; left: 20px; z-index: 99999; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
    .bluepick__fab { width: 48px; height: 48px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 6px 22px rgba(0, 0, 0, 0.32); cursor: pointer; padding: 0; }
    .bluepick__panel { width: 236px; background: #fff; border-radius: 14px; box-shadow: 0 12px 44px rgba(0, 0, 0, 0.28); padding: 16px; }
    .bluepick__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .bluepick__title { font-size: 13px; font-weight: 600; color: #1a1a1a; }
    .bluepick__collapse { width: 26px; height: 26px; border: none; background: #eee; border-radius: 7px; cursor: pointer; font-size: 18px; line-height: 1; color: #444; }
    .bluepick__collapse:hover { background: #e0e0e0; }
    .bluepick__swatch { width: 100%; height: 46px; border: 1px solid #ddd; border-radius: 9px; cursor: pointer; padding: 3px; background: #fff; }
    .bluepick__row { display: flex; gap: 8px; margin-top: 12px; }
    .bluepick__hex { flex: 1; min-width: 0; padding: 9px 10px; border: 1px solid #ddd; border-radius: 8px; font-family: ui-monospace, monospace; font-size: 13px; text-transform: uppercase; color: #1a1a1a; }
    .bluepick__hex:focus { outline: none; border-color: #999; }
    .bluepick__copy { padding: 9px 13px; border: none; background: #111; color: #fff; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .bluepick__copy:hover { background: #2a2a2a; }
    .bluepick__reset { width: 100%; margin-top: 10px; padding: 9px; border: 1px solid #ddd; background: #f6f6f6; border-radius: 8px; font-size: 12.5px; font-weight: 500; cursor: pointer; color: #333; }
    .bluepick__reset:hover { background: #efefef; }
</style>

<div class="bluepick" x-data="bluePicker" x-cloak>
    <button x-show="!open" class="bluepick__fab" x-on:click="open = true" :style="'background:' + color" title="Choisir le bleu"></button>

    <div x-show="open" class="bluepick__panel">
        <div class="bluepick__head">
            <span class="bluepick__title">Couleur du bleu</span>
            <button class="bluepick__collapse" x-on:click="open = false" aria-label="Replier">&minus;</button>
        </div>
        <input type="color" class="bluepick__swatch" x-model="color" x-on:input="apply()">
        <div class="bluepick__row">
            <input type="text" class="bluepick__hex" x-model="color" x-on:input="apply()" maxlength="7" spellcheck="false">
            <button class="bluepick__copy" x-on:click="copy()" x-text="copied ? 'Copié !' : 'Copier'"></button>
        </div>
        <button class="bluepick__reset" x-on:click="reset()">Réinitialiser le bleu</button>
    </div>
</div>
