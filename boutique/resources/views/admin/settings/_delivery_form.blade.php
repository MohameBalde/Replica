@php $id = $method->exists ? 'm'.$method->id : 'new'; @endphp
<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($verb === 'PUT') @method('PUT') <input type="hidden" name="_method_id" value="{{ $method->id }}"> @endif

    <div class="field">
        <label class="label" for="{{ $id }}-name">Nom</label>
        <input class="input" id="{{ $id }}-name" name="name" value="{{ $method->name }}" required maxlength="80" placeholder="Ex. Livraison Ratoma">
    </div>

    <fieldset class="field">
        <legend class="label">Type</legend>
        <div class="grid grid-cols-2 gap-2">
            <label class="choice"><input type="radio" name="type" value="delivery" @checked($method->type === 'delivery')> Livraison</label>
            <label class="choice"><input type="radio" name="type" value="pickup" @checked($method->type === 'pickup')> Retrait</label>
        </div>
    </fieldset>

    <div class="grid grid-cols-2 gap-3">
        <div class="field">
            <label class="label" for="{{ $id }}-fee">Frais</label>
            <div class="input-group"><input class="input" id="{{ $id }}-fee" name="fee" inputmode="numeric" value="{{ $method->fee ? gnf($method->fee, false) : '' }}" placeholder="0"><span class="input-addon">GNF</span></div>
        </div>
        <div class="field">
            <label class="label" for="{{ $id }}-min">Commande min.</label>
            <div class="input-group"><input class="input" id="{{ $id }}-min" name="min_order" inputmode="numeric" value="{{ $method->min_order ? gnf($method->min_order, false) : '' }}" placeholder="0"><span class="input-addon">GNF</span></div>
        </div>
    </div>

    <label class="mb-3 flex items-center gap-3">
        <span class="flex-1">
            <span class="block font-semibold">Paiement à la livraison possible</span>
            <span class="text-sm text-text-muted">Coupez pour les zones lointaines : le client paiera avant.</span>
        </span>
        <span class="switch"><input type="checkbox" role="switch" name="allows_cash_on_delivery" value="1" @checked($method->allows_cash_on_delivery)><span></span></span>
    </label>

    @if ($method->exists)
        <label class="mb-4 flex items-center gap-3">
            <span class="flex-1 font-semibold">Proposé aux clients</span>
            <span class="switch"><input type="checkbox" role="switch" name="is_active" value="1" @checked($method->is_active)><span></span></span>
        </label>
    @else
        <input type="hidden" name="is_active" value="1">
    @endif

    <button class="btn btn-primary" type="submit">{{ $method->exists ? 'Enregistrer' : 'Ajouter' }}</button>
</form>
