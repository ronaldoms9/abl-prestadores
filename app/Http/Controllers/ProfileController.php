<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Http\Requests\PasswordRequest;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Show the form for editing the profile.
     *
     * @return \Illuminate\View\View
     */
    public function edit()
    {
        return view('profile.edit');
    }

    /**
     * Update the profile
     *
     * @param  \App\Http\Requests\ProfileRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProfileRequest $request)
    {
        try{
            DB::beginTransaction();


            $validator = Validator::make($request->all(), [
                'name'=> 'required|min:3',
                'email'=> "required|email|unique:users,email,".auth()->user()->id,
                'cpf'=> "nullable|unique:users,cpf,".auth()->user()->id,
                'telefone'=> 'nullable',
            ]);

            $validator->after(function($validator) use($request) {
                if(!empty($request->cpf) &&  !validaCPF($request->cpf)) {
                    $validator->errors()->add('cpf', 'Informe um CPF Válido');
                }
            });

            if($validator->fails())
                return back()->withErrors($validator->errors());

            auth()->user()->update($request->all());

            DB::commit();
            return back()->with('success',__('Dados atualizados com sucesso.'));
        }catch (\Exception $e){
            report($e);
            DB::rollBack();
            return back()->with('error', $e->getCode() == -1 ? $e->getMessage() : 'Ocorreu um erro ao tentar atualizar os seus dados');
        }

    }

    /**
     * Change the password
     *
     * @param  \App\Http\Requests\PasswordRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function password(PasswordRequest $request)
    {
        auth()->user()->update(['password' => Hash::make($request->validated('password'))]);
        Auditoria::registrar('usuario-senha-alterada', auth()->user());

        return back()->with('success', 'Senha atualizada com sucesso');
    }

    public function updatePhoto(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'foto' => 'required|image'
            ]);

            if($validator->fails())
                throw new \Exception(implode(';', $validator->errors()->all()), -1);

            $path = Storage::disk('public')->put('users', $request->foto);

            $old_img = auth()->user()->photo;


            User::findOrFail(auth()->user()->id)->update(['photo' => $path]);

            if($old_img){
                Storage::disk('public')->delete($old_img);
            }

            return back()->with('success', 'Sua foto foi alterada');
        }catch (\Exception $e){
            report($e);
            DB::rollBack();
            return back()->with('error', $e->getCode() == -1 ? $e->getMessage() : 'Ocorreu um erro ao tentar atualizar os seus dados');
        }
    }
}
