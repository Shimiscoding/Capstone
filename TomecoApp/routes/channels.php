<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('team.{teamId}.locations', function ($user, $teamId): bool {
    return $user->isAdmin() || ($user->isSupervisor() && (int) $user->id === (int) $teamId);
});
