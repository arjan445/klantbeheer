<?php

namespace App\Controllers;

/**
 * Eigen wachtwoord wijzigen.
 */
class Account extends BaseController
{
    public function index()
    {
        if (! $this->request->is('post')) {
            return $this->page('users/account', [], 'Wachtwoord wijzigen');
        }

        $post = $this->request->getPost(['current', 'password', 'password_confirm']);
        $rules = [
            'current'          => 'required',
            'password'         => 'required|min_length[10]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validateData($post, $rules, ['password_confirm' => ['matches' => 'De wachtwoorden zijn niet gelijk.']])) {
            return $this->page('users/account', ['validation' => true], 'Wachtwoord wijzigen');
        }

        $user = auth()->user();
        if (! service('passwords')->verify($post['current'], $user->password_hash)) {
            return $this->back('Huidig wachtwoord klopt niet.', 'error');
        }

        $user->fill(['password' => $post['password']]);
        auth()->getProvider()->save($user);

        return $this->back('Wachtwoord gewijzigd.', 'message', site_url('/'));
    }
}
