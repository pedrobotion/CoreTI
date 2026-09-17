{{-- Seletor de itens por página, compartilhado pelas listagens dos módulos. --}}
<select name="per_page" class="rounded-md border-slate-300 text-sm" onchange="this.form.submit()">
    @foreach (\App\Support\Paginacao::OPCOES as $opcao)
        <option value="{{ $opcao }}" @selected(\App\Support\Paginacao::normalizar(request('per_page')) === $opcao)>{{ $opcao }} por página</option>
    @endforeach
</select>
