<?php
namespace PayMe\Remotisan;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Models\ProcessStatuses;
use PayMe\Remotisan\Models\Audit;

class Remotisan
{

    private CommandsRepository $commandsRepo;
    /** @var callable[] */
    private static array $authWith = [];
    private ProcessExecutor $processExecutor;

    protected static $userIdentifierGetter;

    protected string $instance_uuid;

    /**
     * @param CommandsRepository $commandsRepo
     * @param ProcessExecutor    $processExecutor
     */
    public function __construct(CommandsRepository $commandsRepo, ProcessExecutor $processExecutor)
    {
        $this->commandsRepo = $commandsRepo;
        $this->processExecutor = $processExecutor;
        $this->instance_uuid = Storage::disk("local")->get("remotisan_server_guid");
    }

    /**
     * @param string $command
     * @param string $params
     *
     * @return string
     */
    public function execute(string $command, string $params): string
    {
        if (!$commandData = $this->commandsRepo->find($command)) {
            throw new \RuntimeException("command '{$command}' not allowed");
        }

        $commandData->checkExecute($this->getUserGroup());

        $uuid = Str::uuid()->toString();

        $pid = $this->processExecutor->execute($command, $params, $uuid, $this->getFilePath($uuid));
        $this->audit((int)$pid, $uuid, $this->instance_uuid, time(), $command, $params, $this->getUserIdentifier(), ProcessStatuses::RUNNING);

        return $uuid;
    }

    /**
     * @param int $pid
     * @param string $uuid
     * @param int $timestamp
     * @param string $command
     * @param string $params
     * @param string $userIdentifier
     * @return void
     */
    public function audit(int $pid, string $uuid, string $instanceUuid, int $timestamp, string $command, string $params, string $userIdentifier, int $status): void
    {
        Audit::create([
            "pid"           => $pid,
            "uuid"          => $uuid,
            "instance_uuid" => $instanceUuid,
            "executed_at"   => $timestamp,
            "command"       => $command,
            "parameters"    => $params,
            "user_identifier"=> $userIdentifier,
            "process_status"=> $status,
        ]);
    }

    public function sendKillSignal(string $uuid): string
    {
        $auditRecord = null;
        if(config("remotisan.allow_process_kill", false) === true) {
            $auditRecord = Audit::getByUuid($uuid);
        }

        if (!$auditRecord) {
            throw new RemotisanException("Action Not Allowed.", 404);
        }

        if ($auditRecord->user_identifier != $this->getUserIdentifier()) {
            throw new RemotisanException("Action Not Allowed.", 401);
        }

        if ($auditRecord->process_status !== ProcessStatuses::RUNNING) {
            throw new RemotisanException("Action Not Allowed.", 422);
        }

        $cacheKey = $this->makeCacheKey();
        $values = Cache::get($cacheKey);
        if ($values !== (array)$values) {
            $values = [];
        }

        $values[] = $uuid;
        Cache::put($cacheKey, $uuid);
        return $uuid;
    }

    /**
     * Process killer passthru to process executor.
     * @param string $uuid
     * @return int
     */
    public function killProcess(string $uuid): string
    {
        $auditRecord = null;
        if (config("remotisan.allow_process_kill", false) === true) {
            $auditRecord = Audit::getByUuid($uuid);
        }

        if ($this->instance_uuid !== $auditRecord->getInstanceUuid()) {
            throw new RemotisanException("Action Not Allowed.", 401);
        }

        if (!$this->processExecutor->isOwnedProcess($auditRecord)) {
            throw new RemotisanException("Action Not Allowed.", 401);
        }

        $dateTime = (string)Carbon::parse();
        $this->processExecutor->appendInputToFile($this->getFilePath($uuid), "PROCESS KILLED AT " . $dateTime);

        $this->processExecutor->killProcess($auditRecord);
        $auditRecord->updateProcessStatus(ProcessStatuses::KILLED);

        $cacheKey = $this->makeCacheKey();
        $values = Cache::get($cacheKey);
        if ($values !== (array)$values) {
            $values = [];
        }

        if ($key = array_search($uuid, $values)) {
            unset($values[$key]);
            Cache::put($cacheKey, $values);
        }

        return $uuid;
    }

    public function makeCacheKey(): string
    {
        return implode(":", [config("remotisan.killing_key"), $this->instance_uuid]);
    }

    /**
     * @param $executionUuid
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read($executionUuid): array
    {
        $content = explode(PHP_EOL, rtrim(File::get($this->getFilePath($executionUuid))));
        $lines = count($content);
        $isEnded = false;

        if ($lines > 1 && $content[$lines-1] == $executionUuid) {
            array_pop($content);
            $isEnded = true;
            Artisan::call("remotisan:complete {$executionUuid}");
        }

        return [
            "content" => $content,
            "isEnded" => $isEnded
        ];
    }

    /**
     * @param string $executionUuid
     *
     * @return string
     */
    protected function getFilePath(string $executionUuid): string
    {
        $path = config("remotisan.logger.path");
        File::ensureDirectoryExists($path);

        return $path.$executionUuid.'.log';
    }

    /**
     * @param          $role
     * @param callable $callable
     *
     * @return void
     */
    public function authWith($role, callable $callable): void
    {
        static::$authWith[] = ["role" => $role, "callable" => $callable];
    }

    /**
     * @return string
     */
    public function getUserIdentifier(): ?string
    {
        $callable = static::$userIdentifierGetter;
        if($callable === null) {
            return null;
        }

        $request = Request::instance();
        return call_user_func_array(static::$userIdentifierGetter, [$request]);
    }

    /**
     * @param callable $userIdentifierGetter
     * @return void
     */
    public function setUserIdentifierGetter(callable $userIdentifierGetter):void
    {
        static::$userIdentifierGetter = $userIdentifierGetter;
    }

    /**
     * @return string|null
     */
    public function getUserGroup(): ?string
    {
        $request = Request::instance();

        return collect(static::$authWith)
                   ->first(function (array $roleData) use ($request) {
                       return call_user_func_array($roleData["callable"], [$request]);
                   })["role"] ?? null;
    }

    /**
     * @return void
     * @throws UnauthenticatedException
     */
    public function requireAuthenticated(): void
    {
        if (! $this->getUserGroup()) {
            throw new UnauthenticatedException();
        }
    }
}
