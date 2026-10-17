@extends('layouts.app', ['title' => 'Carregar planilha de pessoas'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="card shadow mb-3">
                    <div class="card-header border-0">
                        <h3 class="card-title mb-0">Nova carga</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Planilha .xlsx ou .csv com as colunas <strong>cpf</strong> e <strong>cnpj</strong> na primeira linha,
                            até {{ number_format($maxLinhas, 0, ',', '.') }} linhas e 2 MB. Cada linha pode ter o CPF, o CNPJ ou os dois;
                            com os dois, o sistema sugere o vínculo entre a pessoa e a empresa dela. Carregar a mesma planilha de novo não duplica nada.
                        </p>
                        <form method="POST" action="{{ route('importacao-pessoa.store') }}" enctype="multipart/form-data"
                              class="row g-2 align-items-start">
                            @csrf
                            <div class="col-md-6">
                                <label for="arquivo" class="small text-semibold">Planilha</label>
                                <input id="arquivo" type="file" name="arquivo" accept=".xlsx,.csv" required
                                       class="form-control @error('arquivo') is-invalid @enderror">
                                @error('arquivo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 d-flex gap-2 align-items-end" style="padding-top: 1.6rem">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Carregar
                                </button>
                                <a href="{{ route('importacao-pessoa.modelo') }}" class="btn btn-link">
                                    <i class="bi bi-download"></i> Baixar modelo
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="card-title mb-0">Cargas anteriores</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive pr-2 pl-2">
                            {!! $html->table(['style' => 'width: 100%'], true) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    {!! $html->scripts() !!}
@endpush
