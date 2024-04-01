<?php declare(strict_types=1);
namespace Kingsoft\Http;

interface RestInterface
{
  public function get(): void;
  public function post(): void;
  public function put(): void;
  public function delete(): void;
  public function head(): void;
}