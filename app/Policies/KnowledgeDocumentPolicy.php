<?php

namespace App\Policies;

use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KnowledgeDocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\KnowledgeDocument  $knowledgeDocument
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, KnowledgeDocument $knowledgeDocument)
    {
        return $user->tenant_id === $knowledgeDocument->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\KnowledgeDocument  $knowledgeDocument
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, KnowledgeDocument $knowledgeDocument)
    {
        return $user->tenant_id === $knowledgeDocument->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\KnowledgeDocument  $knowledgeDocument
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, KnowledgeDocument $knowledgeDocument)
    {
        return $user->tenant_id === $knowledgeDocument->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\KnowledgeDocument  $knowledgeDocument
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, KnowledgeDocument $knowledgeDocument)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\KnowledgeDocument  $knowledgeDocument
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, KnowledgeDocument $knowledgeDocument)
    {
        //
    }
}
