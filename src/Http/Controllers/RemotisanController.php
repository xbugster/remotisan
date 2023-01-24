<?php

namespace PayMe\Remotisan\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Exceptions\ProcessFailedException;
use PayMe\Remotisan\Models\Audit;
use PayMe\Remotisan\Remotisan;

class RemotisanController extends Controller {

    protected Remotisan $rt;
    protected CommandsRepository $commandsRepo;

    /**
     * @param Remotisan          $rt
     * @param CommandsRepository $commandsRepo
     */
    public function __construct(Remotisan $rt, CommandsRepository $commandsRepo)
    {
        $this->rt = $rt;
        $this->commandsRepo = $commandsRepo;
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $this->rt->requireAuthenticated();

        return view('remotisan::index');
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function commands(Request $request): array
    {
        $this->rt->requireAuthenticated();

        return [
            "commands" => $this->commandsRepo->allByRole($this->rt->getUserGroup())
        ];
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function execute(Request $request): array
    {
        $this->rt->requireAuthenticated();

        $command = $request->json("command");
        $params  = $request->json("params");

        return [
            "id" => $this->rt->execute($command, $params)
        ];
    }

    /**
     * Kill process endpoint. If PID returned, then process killed.
     * @param Request $request
     * @return array
     */
    public function killProcess(Request $request): array
    {
        try {
            $pid = $this->rt->killProcess($request->uuid);
        } catch (ProcessFailedException $e) {
            $pid = null;
        }

        return ["pid" => $pid];
    }

    /**
     * @param Request $request
     * @return Collection
     */
    public function history(Request $request): Collection
    {
        return Audit::query()
            ->where("user_identifier", $this->getUserIdentifier())
            ->orderByDesc("executed_at")
            ->limit(config("remotisan.show_history_records_num"))
            ->get();
    }

    /**
     * @param Request $request
     * @param         $uuid
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read(Request $request, $uuid): array
    {
        $this->rt->requireAuthenticated();

        return $this->rt->read($uuid);
    }
}
