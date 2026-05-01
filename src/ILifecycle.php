<?php
namespace AlfacodeTeam\PhpIoCli;
interface ILifecycle
{
   public function mount(): void;
    public function render(): void;
    public function update(string $key): void;
    public function destroy(): void;
}