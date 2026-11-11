<?php

namespace App\Controllers;

use CodeIgniter\Shield\Entities\User;

/**
 * Gebruikers beheren (alleen admin / users.manage).
 */
class Users extends BaseController
{
    public function index()
    {
        $users = auth()->getProvider()->withIdentities()->withGroups()->orderBy('id')->findAll();

        return $this->page('users/index', ['users' => $users, 'groups' => config('AuthGroups')->groups], 'Gebruikers');
    }

    public function create()
    {
        $groups = config('AuthGroups')->groups;

        if (! $this->request->is('post')) {
            return $this->page('users/form', ['user' => null, 'groups' => $groups], 'Nieuwe gebruiker');
        }

        $post = $this->request->getPost(['email', 'username', 'password', 'group']);
        $rules = [
            'email'    => 'required|valid_email|is_unique[auth_identities.secret]',
            'username' => 'permit_empty|alpha_numeric_punct|min_length[3]|max_length[30]|is_unique[users.username]',
            'password' => 'required|min_length[10]',
            'group'    => 'required|in_list[' . implode(',', array_keys($groups)) . ']',
        ];
        if (! $this->validateData($post, $rules)) {
            return $this->page('users/form', ['user' => null, 'groups' => $groups, 'old' => $post, 'validation' => true], 'Nieuwe gebruiker');
        }

        $provider = auth()->getProvider();
        $user = new User([
            'email'    => $post['email'],
            'username' => $post['username'] ?: null,
            'password' => $post['password'],
            'active'   => 1,
        ]);
        $provider->save($user);
        $user = $provider->findById($provider->getInsertID());
        $user->syncGroups($post['group']);

        return $this->back('Gebruiker aangemaakt.', 'message', site_url('beheer/gebruikers'));
    }

    public function edit($id)
    {
        $groups   = config('AuthGroups')->groups;
        $provider = auth()->getProvider();
        $user     = $provider->findById($id);
        if ($user === null) {
            return $this->back('Gebruiker niet gevonden.', 'error', site_url('beheer/gebruikers'));
        }

        if (! $this->request->is('post')) {
            return $this->page('users/form', ['user' => $user, 'groups' => $groups], 'Gebruiker bewerken');
        }

        $post  = $this->request->getPost(['group', 'password', 'active']);
        $rules = [
            'group'    => 'required|in_list[' . implode(',', array_keys($groups)) . ']',
            'password' => 'permit_empty|min_length[10]',
        ];
        if (! $this->validateData($post, $rules)) {
            return $this->page('users/form', ['user' => $user, 'groups' => $groups, 'validation' => true], 'Gebruiker bewerken');
        }

        $isSelf = (int) $id === (int) auth()->id();
        if ($isSelf && ($post['group'] !== 'admin' || ! $post['active'])) {
            return $this->back('Je kunt jezelf geen admin-rechten afnemen of deactiveren.', 'error');
        }

        $user->syncGroups($post['group']);
        $post['active'] ? $user->activate() : $user->deactivate();
        if ($post['password'] !== '') {
            $user->fill(['password' => $post['password']]);
            $provider->save($user);
        }

        return $this->back('Gebruiker opgeslagen.', 'message', site_url('beheer/gebruikers'));
    }
}
