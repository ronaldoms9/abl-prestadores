<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Role;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

class UserController extends Controller
{
    /**
     * Display a listing of the users
     *
     * @param  \App\Models\User  $model
     * @return \Illuminate\View\View
     */
    public function index(Builder $builder)
    {
        if (request()->ajax()) {

            $query = User::query()
                ->when(request('situacao') === 'ativos', fn ($q) => $q->where('ativo', true))
                ->when(request('situacao') === 'inativos', fn ($q) => $q->where('ativo', false));

            return DataTables::of($query)
                ->editColumn('ativo', function ($model) {
                    return $model->ativo
                        ? '<span class="badge text-bg-success">Ativo</span>'
                        : '<span class="badge text-bg-secondary">Inativo</span>';
                })
                ->editColumn('created_at', function($model){
                    return $model->created_at->format('d/m/Y H:i');
                })
                ->editColumn('permission', function($model){
                    return $model->roles()->first()->display_name ?? 'Sem permissão';
                })
                ->addColumn('action', function ($model)  {
                    return view('config.users.action', [
                        'model' => $model,
                    ]);
                })
                ->rawColumns(['ativo', 'action'])
                ->make(true);
        }
        $html = $builder->columns([



            ['data' => 'name', 'name' => 'name', 'title' => 'Nome','class'=> 'text-semibold',],
            ['data' => 'email', 'name' => 'email', 'title' => 'Email',],
            ['data' => 'permission', 'name' => 'permission', 'title' => 'Perfil', 'searchable'     => false, 'orderable' => false,],
            ['data' => 'ativo', 'name' => 'ativo', 'title' => 'Ativo', 'searchable'     => false,],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Cadastrado em', 'searchable'     => false,],
            [
                'title'          => 'Ações',
                'data'           => 'action',
                'name'           => 'action',
                'orderable'      => false,
                'searchable'     => false,
                'exportable'     => false,
                'class'          => 'td-actions '
            ],

        ])->ajaxWithForm(route('user.index'), '#filtros-usuarios')->parameters([
            'id'=> 'dataTableTeste',
            'searchDelay' => 300,
            'language' => [
                'url' => url('js/datatable.json')
            ],
            'responsive' => true,
            'scrollX' => true,

			'pageLength' => 25


        ]);

        return view('config.users.index', ['html'=> $html]);
    }

    /**
     * Retorna formulário de cadastro do usuário!
     *
     * @param int $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function form($id = 0)
    {
        $user = $id ? User::findOrFail($id) : new User();

        return view('config.users.form',['user'=> $user, 'grupos' => Role::all()]);
    }


    /**
     * Cria e edita usuários após validar regras de cadastro.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id = 0)
    {
        try{

            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'nome'=> 'required',
                'email'=> "required|email|unique:users,email,{$id}",
                'cpf'=> "nullable|unique:users,cpf,{$id}",
                'senha'=> [$id ? 'nullable' : 'required', Password::defaults()],
                'telefone'=> 'nullable',
                'grupo_acesso'=> 'required',
            ]);
            if($validator->fails())
                throw new \Exception(implode('; ', $validator->errors()->all()),-1);



            if(!empty($request->cpf) &&  !validaCPF($request->cpf)) {
                throw new \Exception('Informe um CPF válido!',-1);
            }

            // Sem o campo, mantém a situação atual (novo usuário nasce ativo).
            $ativo = $request->boolean('ativo', $id ? (bool) User::findOrFail($id)->ativo : true);

            if ($id == auth()->id() && ! $ativo) {
                throw new \Exception('Você não pode desativar o próprio acesso.', -1);
            }

            $array_store =[
                'name'=> $request->nome,
                'email'=> $request->email,
                'cpf'=> filled($request->cpf) ? filtraNumeros($request->cpf) : null,
                'telefone'=> $request->telefone,
                'ativo'=> $ativo,
            ];

            if(isset($request->senha) && trim($request->senha)) {
                $array_store['password'] = Hash::make($request->senha);
            }

            if($id != 0) {
                $user = User::findOrFail($id);
                $user->update($array_store);

                if (isset($array_store['password'])) {
                    Auditoria::registrar('usuario-senha-alterada', $user);
                }
            }else {
                //INSERT
                $user = User::create($array_store);
            }

            $perfisAntes = $user->roles()->orderBy('display_name')->pluck('display_name')->all();
            $user->roles()->sync([$request->grupo_acesso]);
            $perfisDepois = $user->roles()->orderBy('display_name')->pluck('display_name')->all();

            if ($perfisAntes !== $perfisDepois) {
                Auditoria::registrar('usuario-perfil-alterado', $user, ['antes' => $perfisAntes, 'depois' => $perfisDepois]);
            }


            DB::commit();
            return response()->json(['success'=> true, 'message' => '', 'data' => []]);
        }catch (\Exception $e){
            report($e);
            DB::rollBack();
            return response()->json(['success'=> false, 'message' => $e->getCode() == -1 ? $e->getMessage() : 'Ocorreu um erro, informe à administração!', 'data' => null ], 400);
        }
    }

    /**
     * Apaga usuario
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try{
            DB::beginTransaction();
            if ($id == auth()->id()) {
                throw new \Exception('Você não pode excluir o próprio usuário.', -1);
            }

            $usuario = User::findOrFail($id);
            $usuario->delete();

            DB::commit();
            return response()->json(['success'=> true,'message'=> 'Registro excluído com sucesso!', 'data' => null ], 201);
        }catch (\Exception $e){
            report($e);
            DB::rollBack();
            return response()->json(['success'=> false,'message'=> $e->getCode() == -1 ? $e->getMessage() : 'Ocorreu um erro, informe à administração!', 'data' => null ], 400);
        }

    }
}
