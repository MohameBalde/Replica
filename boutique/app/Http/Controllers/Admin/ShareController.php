<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Support\Period;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * S22 : « Partager ma boutique ». Le pont entre les pubs et la boutique :
 * un lien par réseau (avec ?src=), le lien de chaque produit, le message
 * d'accueil à coller dans WhatsApp Business / Messenger, un QR code, et
 * ce que chaque réseau rapporte (visites -> commandes).
 */
class ShareController extends Controller
{
    public const NETWORKS = [
        'facebook' => ['Facebook', 'Bouton de la pub, publication, page'],
        'tiktok' => ['TikTok', 'Bio, commentaire épinglé'],
        'whatsapp' => ['WhatsApp', 'Statut, groupes, message d\'accueil'],
        'instagram' => ['Instagram', 'Bio, story'],
        'messenger' => ['Messenger', 'Réponse automatique de la page'],
    ];

    public function show(Request $request): View
    {
        $store = $this->currentStore($request);
        // Même période que l'Accueil quand on vient de son lien « Voir le détail ».
        $days = Period::days($request->query('periode'));
        $since = Period::start($days);

        $visits = $store->dailyVisits()->where('day', '>=', $since->toDateString())
            ->selectRaw('source, SUM(visits) as n')->groupBy('source')->pluck('n', 'source');
        $orders = $store->orders()->counted()->where('created_at', '>=', $since->utc())
            ->selectRaw('source, COUNT(*) as n, SUM(total) as revenue')->groupBy('source')->get()->keyBy('source');

        $stats = collect(Order::SOURCES)->map(fn ($label, $key) => [
            'label' => $label,
            'visits' => (int) ($visits[$key] ?? 0),
            'orders' => (int) ($orders[$key]->n ?? 0),
            'revenue' => (int) ($orders[$key]->revenue ?? 0),
        ])->filter(fn ($s) => $s['visits'] || $s['orders']);

        return view('admin.share', [
            'store' => $store,
            'links' => collect(self::NETWORKS)->map(fn ($n, $key) => [
                'label' => $n[0], 'where' => $n[1], 'url' => route('shop.show', $store).'?src='.$key,
            ]),
            'products' => $store->products()->where('is_visible', true)->orderBy('position')->orderByDesc('id')->limit(50)->get(),
            'stats' => $stats,
            'days' => $days,
        ]);
    }

    public function updateWelcome(Request $request): RedirectResponse
    {
        $data = $request->validate(['welcome_message' => ['required', 'string', 'max:1000']]);
        $this->currentStore($request)->update($data);

        return back()->with('status', 'Message d\'accueil enregistré.');
    }

    /** QR code de la boutique (SVG net à toutes les tailles, à imprimer pour le comptoir). */
    public function qr(Request $request): Response
    {
        $store = $this->currentStore($request);
        $writer = new Writer(new ImageRenderer(new RendererStyle(480, 2), new SvgImageBackEnd()));
        $svg = $writer->writeString(route('shop.show', $store).'?src=qr');

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="qr-'.$store->slug.'.svg"',
        ]);
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
