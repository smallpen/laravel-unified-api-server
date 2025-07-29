<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActionRegistry;
use App\Contracts\ActionInterface;
use App\Contracts\ResponseFormatterInterface;
use App\Contracts\PermissionCheckerInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 統一API控制器
 * 
 * 處理所有透過統一接口路徑進入的API請求
 * 根據action_type參數路由到對應的Action處理器
 */
class UnifiedApiController extends Controller
{
    /**
     * Action註冊系統
     * 
     * @var ActionRegistry
     */
    protected ActionRegistry $actionRegistry;

    /**
     * 回應格式化器
     * 
     * @var ResponseFormatterInterface
     */
    protected ResponseFormatterInterface $responseFormatter;

    /**
     * 權限檢查器
     * 
     * @var PermissionCheckerInterface
     */
    protected PermissionCheckerInterface $permissionChecker;

    /**
     * 建構函式
     * 
     * @param ActionRegistry $actionRegistry Action註冊系統
     * @param ResponseFormatterInterface $responseFormatter 回應格式化器
     * @param PermissionCheckerInterface $permissionChecker 權限檢查器
     */
    public function __construct(
        ActionRegistry $actionRegistry, 
        ResponseFormatterInterface $responseFormatter,
        PermissionCheckerInterface $permissionChecker
    ) {
        $this->actionRegistry = $actionRegistry;
        $this->responseFormatter = $responseFormatter;
        $this->permissionChecker = $permissionChecker;
    }
    /**
     * 處理統一API請求
     * 
     * 所有API請求都透過此方法進入系統
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // 驗證請求方法必須為POST
            if (!$request->isMethod('POST')) {
                return $this->jsonResponse(
                    $this->responseFormatter->error(
                        '不支援的請求方法，僅允許POST請求',
                        'METHOD_NOT_ALLOWED'
                    ),
                    405
                );
            }

            // 驗證必要參數
            $this->validateRequiredParameters($request);

            // 提取action_type參數
            $actionType = $request->input('action_type');

            // 路由到對應的Action處理器
            return $this->routeToAction($actionType, $request);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            // 記錄錯誤日誌
            \Log::error('統一API控制器發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'user_id' => $request->user()?->id,
            ]);

            return $this->jsonResponse(
                $this->responseFormatter->error(
                    '系統內部錯誤',
                    'INTERNAL_SERVER_ERROR'
                ),
                500
            );
        }
    }

    /**
     * 驗證必要參數
     * 
     * @param \Illuminate\Http\Request $request
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequiredParameters(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'action_type' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_\-\.]+$/', // 只允許字母、數字、底線、連字號和點
            ],
        ], [
            'action_type.required' => 'action_type 參數為必填項目',
            'action_type.string' => 'action_type 必須為字串格式',
            'action_type.max' => 'action_type 長度不能超過100個字元',
            'action_type.regex' => 'action_type 格式不正確，只能包含字母、數字、底線、連字號和點',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * 路由到對應的Action處理器
     * 
     * @param string $actionType
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function routeToAction(string $actionType, Request $request): JsonResponse
    {
        try {
            // 檢查Action是否存在
            if (!$this->actionRegistry->hasAction($actionType)) {
                return $this->jsonResponse(
                    $this->responseFormatter->error(
                        "找不到指定的Action: {$actionType}",
                        'ACTION_NOT_FOUND'
                    ),
                    404
                );
            }

            // 解析Action實例
            $action = $this->actionRegistry->resolve($actionType);

            // 檢查Action是否啟用
            if (!$action->isEnabled()) {
                return $this->jsonResponse(
                    $this->responseFormatter->error(
                        "Action已停用: {$actionType}",
                        'ACTION_DISABLED'
                    ),
                    403
                );
            }

            // 取得已驗證的使用者
            $user = $request->user();
            if (!$user) {
                return $this->jsonResponse(
                    $this->responseFormatter->error(
                        '使用者未驗證',
                        'USER_NOT_AUTHENTICATED'
                    ),
                    401
                );
            }

            // 檢查使用者權限
            if (!$this->permissionChecker->canExecuteAction($user, $action)) {
                // 記錄權限拒絕
                $this->permissionChecker->logPermissionDenied(
                    $user, 
                    $actionType, 
                    $action->getRequiredPermissions()
                );

                return $this->jsonResponse(
                    $this->responseFormatter->error(
                        '權限不足，無法執行此Action',
                        'INSUFFICIENT_PERMISSIONS'
                    ),
                    403
                );
            }

            // 驗證請求參數
            $action->validate($request);

            // 執行Action
            $result = $action->execute($request, $user);

            // 記錄執行日誌
            \Log::info('Action執行成功', [
                'action_type' => $actionType,
                'user_id' => $user->id,
            ]);

            return $this->jsonResponse(
                $this->responseFormatter->success($result, 'Action執行成功')
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(
                $this->responseFormatter->error(
                    $e->getMessage(),
                    'INVALID_ACTION'
                ),
                400
            );
        } catch (\Exception $e) {
            // 記錄錯誤日誌
            \Log::error('Action執行失敗', [
                'action_type' => $actionType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return $this->jsonResponse(
                $this->responseFormatter->error(
                    'Action執行過程發生錯誤',
                    'ACTION_EXECUTION_ERROR'
                ),
                500
            );
        }
    }



    /**
     * 回傳JSON回應
     * 
     * @param array $data 回應資料陣列
     * @param int $statusCode HTTP狀態碼
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonResponse(array $data, int $statusCode = 200): JsonResponse
    {
        return response()->json($data, $statusCode);
    }

    /**
     * 處理驗證錯誤回應
     * 
     * @param \Illuminate\Validation\ValidationException $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return $this->jsonResponse(
            $this->responseFormatter->validationError($exception->errors()),
            422
        );
    }
}