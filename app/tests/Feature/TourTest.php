<?php

use App\Models\User;

// ─── PATCH /studio/tour-complete ─────────────────────────────────────────────

it('imposta tour_completed=true per l\'utente autenticato', function () {
    $user = User::factory()->create(['tour_completed' => false]);

    $this->actingAs($user)
        ->patchJson('/studio/tour-complete')
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($user->fresh()->tour_completed)->toBe(true);
});

it('non modifica tour_completed di altri utenti', function () {
    $user  = User::factory()->create(['tour_completed' => false]);
    $other = User::factory()->create(['tour_completed' => false]);

    $this->actingAs($user)->patchJson('/studio/tour-complete');

    expect($other->fresh()->tour_completed)->toBe(false);
});

it('restituisce 401 se non autenticato', function () {
    $this->patchJson('/studio/tour-complete')->assertUnauthorized();
});

it('è idempotente: chiamate multiple non causano errori', function () {
    $user = User::factory()->create(['tour_completed' => true]);

    $this->actingAs($user)
        ->patchJson('/studio/tour-complete')
        ->assertOk();

    expect($user->fresh()->tour_completed)->toBe(true);
});

// ─── User model ───────────────────────────────────────────────────────────────

it('tour_completed è false per default al factory', function () {
    $user = User::factory()->create();
    expect($user->tour_completed)->toBe(false);
});

it('isAdmin() ritorna true se role === admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    expect($admin->isAdmin())->toBe(true);
});

it('isAdmin() ritorna false se role === operator', function () {
    $operator = User::factory()->create(['role' => 'operator']);
    expect($operator->isAdmin())->toBe(false);
});

it('studio3ghdTourPending nel layout è true se tour_completed è false', function () {
    $user = User::factory()->create(['tour_completed' => false]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSee('window.studio3ghdTourPending = true', false);
});

it('studio3ghdTourPending nel layout è false se tour_completed è true', function () {
    $user = User::factory()->create(['tour_completed' => true]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSee('window.studio3ghdTourPending = false', false);
});
