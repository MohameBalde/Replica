{{-- Alerte « nouvelle commande » : à activer sur chaque appareil (téléphone, ordinateur). Sans JavaScript, rien ne s'affiche. --}}
<div class="mx-4 mt-3 rounded-md border border-border bg-bg p-3 lg:mt-4" data-push hidden
     data-key="{{ \App\Services\PushNotifier::publicKey() }}"
     data-subscribe="{{ route('admin.push.store') }}" data-unsubscribe="{{ route('admin.push.destroy') }}" data-test="{{ route('admin.push.test') }}">
    <p class="font-semibold">🔔 <span data-push-title>Soyez prévenu de chaque commande</span></p>
    <p class="mt-1 text-sm text-text-muted" data-push-text aria-live="polite">Une alerte sur cet appareil dès qu'un client commande, même s'il n'envoie pas le message WhatsApp.</p>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-primary w-auto px-5" data-push-on>Activer les alertes</button>
        <button type="button" class="btn btn-secondary w-auto px-5" data-push-test hidden>Envoyer une alerte d'essai</button>
        <button type="button" class="btn-ghost inline-flex min-h-tap items-center px-2 text-sm font-semibold text-text-muted" data-push-off hidden>Désactiver</button>
    </div>
</div>
<script>
    (() => {
        const box = document.querySelector('[data-push]');
        if (!box) return;
        const pick = (s) => box.querySelector(s);
        const title = pick('[data-push-title]'), text = pick('[data-push-text]');
        const on = pick('[data-push-on]'), test = pick('[data-push-test]'), off = pick('[data-push-off]');
        const csrf = @js(csrf_token());
        const send = (url, method, body) => fetch(url, {
            method, credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        });
        const texts = {
            off: ['Soyez prévenu de chaque commande', "Une alerte sur cet appareil dès qu'un client commande, même s'il n'envoie pas le message WhatsApp."],
            on: ['Alertes activées sur cet appareil', 'Vous êtes prévenu ici à chaque nouvelle commande. Activez-les aussi sur vos autres téléphones.'],
            blocked: ['Alertes bloquées', "Les notifications de Wanllan sont bloquées sur cet appareil. Autorisez-les dans les réglages du navigateur (le cadenas à côté de l'adresse), puis rechargez la page."],
            iphone: ['Alertes sur iPhone', "Ajoutez d'abord Wanllan à l'écran d'accueil (bouton Partager, puis « Sur l'écran d'accueil »). Ouvrez Wanllan depuis l'écran d'accueil, puis activez les alertes."],
            unsupported: ['Alertes indisponibles ici', 'Ce navigateur ne reçoit pas les alertes. Ouvrez Wanllan dans Chrome.'],
        };
        const show = (state) => {
            box.hidden = false;
            on.hidden = state !== 'off';
            test.hidden = off.hidden = state !== 'on';
            [title.textContent, text.textContent] = texts[state];
        };

        const ios = /iPhone|iPad/.test(navigator.userAgent);
        const installed = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            show(ios && !installed ? 'iphone' : 'unsupported');
            return;
        }

        const base64 = box.dataset.key.replace(/-/g, '+').replace(/_/g, '/');
        const key = Uint8Array.from(atob(base64 + '='.repeat((4 - base64.length % 4) % 4)), (c) => c.charCodeAt(0));
        const ready = navigator.serviceWorker.register('/sw.js').then(() => navigator.serviceWorker.ready);

        ready.then(async (registration) => {
            if (Notification.permission === 'denied') return show('blocked');
            const current = await registration.pushManager.getSubscription();
            if (!current) return show('off');
            send(box.dataset.subscribe, 'POST', current.toJSON()).catch(() => {}); // le serveur a peut-être oublié cet appareil
            show('on');
        }).catch(() => show('unsupported'));

        on.addEventListener('click', async () => {
            on.disabled = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') return show(permission === 'denied' ? 'blocked' : 'off');
                const registration = await ready;
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
                if (!(await send(box.dataset.subscribe, 'POST', subscription.toJSON())).ok) throw new Error('refus du serveur');
                show('on');
                send(box.dataset.test, 'POST').catch(() => {});
                text.textContent = "C'est activé : une alerte d'essai arrive dans quelques secondes.";
            } catch (e) {
                text.textContent = "L'activation n'a pas marché. Vérifiez votre connexion et réessayez (en navigation privée, les alertes ne marchent pas).";
            } finally {
                on.disabled = false;
            }
        });

        test.addEventListener('click', async () => {
            test.disabled = true;
            try {
                const result = await (await send(box.dataset.test, 'POST')).json();
                text.textContent = result.sent
                    ? "Alerte d'essai envoyée : elle arrive dans quelques secondes."
                    : "Aucune alerte n'est partie : désactivez puis réactivez les alertes.";
            } catch (e) {
                text.textContent = "L'essai n'a pas marché. Vérifiez votre connexion.";
            } finally {
                test.disabled = false;
            }
        });

        off.addEventListener('click', async () => {
            off.disabled = true;
            try {
                const current = await (await ready).pushManager.getSubscription();
                if (current) {
                    await send(box.dataset.unsubscribe, 'DELETE', { endpoint: current.endpoint }).catch(() => {});
                    await current.unsubscribe();
                }
                show('off');
            } finally {
                off.disabled = false;
            }
        });
    })();
</script>
