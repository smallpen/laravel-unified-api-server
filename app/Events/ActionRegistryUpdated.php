<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Action註冊系統更新事件
 * 
 * 當Action註冊系統發生變更時觸發此事件
 * 用於通知文件生成器清除快取並重新生成文件
 */
class ActionRegistryUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 更新類型
     * 
     * @var string
     */
    public string $updateType;

    /**
     * 受影響的Action類型
     * 
     * @var array
     */
    public array $affectedActions;

    /**
     * 更新時間戳
     * 
     * @var \Carbon\Carbon
     */
    public $timestamp;

    /**
     * 建構子
     * 
     * @param string $updateType 更新類型 (register, unregister, discover)
     * @param array $affectedActions 受影響的Action類型陣列
     */
    public function __construct(string $updateType, array $affectedActions = [])
    {
        $this->updateType = $updateType;
        $this->affectedActions = $affectedActions;
        $this->timestamp = now();
    }

    /**
     * 取得更新類型
     * 
     * @return string
     */
    public function getUpdateType(): string
    {
        return $this->updateType;
    }

    /**
     * 取得受影響的Action
     * 
     * @return array
     */
    public function getAffectedActions(): array
    {
        return $this->affectedActions;
    }

    /**
     * 取得更新時間戳
     * 
     * @return \Carbon\Carbon
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * 檢查是否影響指定的Action
     * 
     * @param string $actionType Action類型
     * @return bool
     */
    public function affectsAction(string $actionType): bool
    {
        return empty($this->affectedActions) || in_array($actionType, $this->affectedActions);
    }
}