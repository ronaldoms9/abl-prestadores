<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Permission;
use App\Role;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

class PermissoesController extends Controller
{
    //

    /**
     * Display a listing of the users
     *
     * @param  \App\Models\User  $model
     * @return \Illuminate\View\View
     */
    public function index(Builder $builder)
    {

        if (request()->ajax()) {

            return DataTables::of(Role::query())
                ->editColumn('created_at', function($model){
                    return $model->created_at->format('d/m/Y H:i');
                })
                ->addColumn('action', function ($model)  {
                    return view('config.grupos.action', [
                        'model' => $model,
                    ]);
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        $html = $builder->columns([

//            ['data' => 'name', 'name' => 'name', 'title' => 'Nome','class'=> 'text-semibold',],
            ['data' => 'display_name', 'name' => 'display_name', 'title' => 'Permissão',],
            ['data' => 'description', 'name' => 'description', 'title' => 'Descrição',],
//            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Cadastrado em', 'searchable'     => false,],
            [
                'title'          => '',
                'data'           => 'action',
                'name'           => 'action',
                'orderable'      => false,
                'searchable'     => false,
                'exportable'     => false,
                'class'          => 'td-actions '
            ],

        ])->parameters([
            'id'=> 'dataTableTeste',
            'searchDelay' => 300,
            'language' => [
                'url' => url('js/datatable.json')
            ],
            'responsive' => true,
            'scrollX' => true,

            'pageLength' => 25


        ]);

        return view('config.grupos.index', ['html'=> $html]);
    }

    /**
     * @param int $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function form($id = 0)
    {
        $grupo = $id ? Role::findOrFail($id) : new Role();

        $permissionsId = $id ? $grupo->permissions()->pluck('id')->toArray() : [];

        $permissoes = Permission::orderBy('module')->get();

        return view('config.grupos.form', ['grupo' => $grupo, 'permissoes' => $permissoes, 'permissionsId' => $permissionsId]);
    }

    public function store(Request $request, $id = 0){
        try{

            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'identificador'=> $id ? 'nullable' : 'required',
                'nome_grupo'=> 'required',
                'descricao'=> "required",
                'permissoes'=> "required",
            ]);
            if($validator->fails())
                throw new \Exception(implode('; ', $validator->errors()->all()),-1);


            $array_data = [
                'display_name' => $request->nome_grupo,
                'description' => $request->descricao,
            ];

            if($id){
                $role = Role::findOrFail($id);
                $role->update($array_data);
            }else{
                $array_data['name'] = strtolower(str_replace(' ', '', $request->identificador));
                $role = Role::create($array_data);
            }

            $mudancas = $role->permissions()->sync($request->permissoes);

            if ($mudancas['attached'] || $mudancas['detached']) {
                $nomes = fn (array $ids) => Permission::whereIn('id', $ids)->orderBy('display_name')->pluck('display_name')->all();

                Auditoria::registrar('perfil-permissoes-alteradas', $role, [
                    'incluidas' => $nomes($mudancas['attached']),
                    'removidas' => $nomes($mudancas['detached']),
                ]);
            }


            DB::commit();
            return response()->json(['success'=> true, 'message' => '', 'data' => $request->all()]);
        }catch (\Exception $e){
            report($e);
            DB::rollBack();
            return response()->json(['success'=> false, 'message' => $e->getCode() == -1 ? $e->getMessage() : 'Ocorreu um erro, informe à administração!', 'data' => null ], 400);
        }
    }

    /**
     * Remove grupo de acesso
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */

    public function delete($id)
    {
        try{
            DB::beginTransaction();
            $reg = Role::findOrFail($id);
            $reg->delete();

            DB::commit();
            return response()->json(['success'=> true,'message'=> 'Registro excluído com sucesso!' ], 201);
        }catch (\Exception $e){
            report($e);
            DB::rollBack();
            return response()->json(['success'=> false,'message'=> $e->getCode() == -1 ? $e->getMessage() : 'Ocorreu um erro, informe à administração!' ], 400);
        }

    }
}
