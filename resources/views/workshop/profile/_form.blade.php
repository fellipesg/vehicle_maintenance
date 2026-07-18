<div>
    <label for="name" class="form-label">Nome da oficina *</label>
    <input type="text" name="name" id="name" value="{{ old('name', $workshop->name ?? '') }}" required class="form-input">
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="phone" class="form-label">Telefone *</label>
        <input type="text" name="phone" id="phone" value="{{ old('phone', $workshop->phone ?? '') }}" required class="form-input">
        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="whatsapp" class="form-label">WhatsApp</label>
        <input type="text" name="whatsapp" id="whatsapp" value="{{ old('whatsapp', $workshop->whatsapp ?? '') }}" class="form-input">
    </div>
</div>

<div>
    <label for="email" class="form-label">E-mail</label>
    <input type="email" name="email" id="email" value="{{ old('email', $workshop->email ?? '') }}" class="form-input">
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="cep" class="form-label">CEP *</label>
        <input type="text" name="cep" id="cep" value="{{ old('cep', $workshop->cep ?? '') }}" required maxlength="8" class="form-input" placeholder="00000000">
        @error('cep')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="street" class="form-label">Rua *</label>
        <input type="text" name="street" id="street" value="{{ old('street', $workshop->street ?? '') }}" required class="form-input">
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="number" class="form-label">Número *</label>
        <input type="text" name="number" id="number" value="{{ old('number', $workshop->number ?? '') }}" required class="form-input">
    </div>
    <div class="sm:col-span-2">
        <label for="complement" class="form-label">Complemento</label>
        <input type="text" name="complement" id="complement" value="{{ old('complement', $workshop->complement ?? '') }}" class="form-input">
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="neighborhood" class="form-label">Bairro *</label>
        <input type="text" name="neighborhood" id="neighborhood" value="{{ old('neighborhood', $workshop->neighborhood ?? '') }}" required class="form-input">
    </div>
    <div>
        <label for="city" class="form-label">Cidade *</label>
        <input type="text" name="city" id="city" value="{{ old('city', $workshop->city ?? '') }}" required class="form-input">
    </div>
    <div>
        <label for="state" class="form-label">UF *</label>
        <input type="text" name="state" id="state" value="{{ old('state', $workshop->state ?? '') }}" required maxlength="2" class="form-input uppercase">
        @error('state')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="facebook" class="form-label">Facebook</label>
        <input type="url" name="facebook" id="facebook" value="{{ old('facebook', $workshop->facebook ?? '') }}" class="form-input">
    </div>
    <div>
        <label for="instagram" class="form-label">Instagram</label>
        <input type="url" name="instagram" id="instagram" value="{{ old('instagram', $workshop->instagram ?? '') }}" class="form-input">
    </div>
</div>
