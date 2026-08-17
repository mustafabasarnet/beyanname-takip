<?php

namespace Config;

use CodeIgniter\Config\View as BaseView;
use CodeIgniter\View\ViewDecoratorInterface;

class View extends BaseView
{
    /**
     * false ise view metodu her çağrı arasında veriyi temizler.
     *
     * @var bool
     */
    public $saveData = true;

    /**
     * Parser filtreleri.
     * DİKKAT: Üst sınıfta tipsiz tanımlı olduğu için burada da tip verilmemeli.
     *
     * @var array<string, (callable(mixed): mixed)&string>
     */
    public $filters = [];

    /**
     * Parser eklentileri.
     *
     * @var array<string, (callable(mixed...): mixed)|((callable(mixed...): mixed)&string)|list<(callable(mixed...): mixed)&string>>
     */
    public $plugins = [];

    /**
     * Görünüm dekoratörleri.
     *
     * @var list<class-string<ViewDecoratorInterface>>
     */
    public array $decorators = [];

    /**
     * Paket/modül görünümlerini ezmek için app/Views altındaki alt klasör.
     * (CodeIgniter 4.7+)
     */
    public string $appOverridesFolder = 'overrides';
}
