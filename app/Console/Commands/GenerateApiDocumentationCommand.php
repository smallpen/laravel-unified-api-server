<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Contracts\DocumentationGeneratorInterface;
use App\Services\ActionRegistry;
use Illuminate\Support\Facades\File;

/**
 * 生成API文件命令
 * 
 * 用於測試和生成API文件的Artisan命令
 */
class GenerateApiDocumentationCommand extends Command
{
    /**
     * 命令名稱和簽名
     *
     * @var string
     */
    protected $signature = 'api:generate-docs 
                            {--format=json : 輸出格式 (json|openapi)}
                            {--output= : 輸出檔案路徑}
                            {--action= : 指定特定Action}
                            {--validate : 驗證文件完整性}
                            {--summary : 只顯示摘要資訊}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '生成API文件並支援多種輸出格式';

    /**
     * 文件生成器
     *
     * @var \App\Contracts\DocumentationGeneratorInterface
     */
    protected DocumentationGeneratorInterface $documentationGenerator;

    /**
     * Action註冊系統
     *
     * @var \App\Services\ActionRegistry
     */
    protected ActionRegistry $actionRegistry;

    /**
     * 建構子
     *
     * @param \App\Contracts\DocumentationGeneratorInterface $documentationGenerator
     * @param \App\Services\ActionRegistry $actionRegistry
     */
    public function __construct(
        DocumentationGeneratorInterface $documentationGenerator,
        ActionRegistry $actionRegistry
    ) {
        parent::__construct();
        $this->documentationGenerator = $documentationGenerator;
        $this->actionRegistry = $actionRegistry;
    }

    /**
     * 執行命令
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('開始生成API文件...');

        try {
            // 自動發現Action
            $this->actionRegistry->autoDiscoverActions();
            $this->info('Action自動發現完成');

            // 根據選項執行不同操作
            if ($this->option('summary')) {
                return $this->handleSummary();
            }

            if ($this->option('validate')) {
                return $this->handleValidation();
            }

            if ($this->option('action')) {
                return $this->handleSingleAction();
            }

            return $this->handleFullDocumentation();

        } catch (\Exception $e) {
            $this->error("生成文件時發生錯誤: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * 處理摘要資訊顯示
     *
     * @return int
     */
    protected function handleSummary(): int
    {
        $summary = $this->documentationGenerator->getActionsSummary();
        
        $this->info('Action摘要資訊:');
        $this->newLine();

        $headers = ['Action Type', 'Name', 'Version', 'Enabled', 'Permissions', 'Parameters'];
        $rows = [];

        foreach ($summary as $actionType => $info) {
            $rows[] = [
                $actionType,
                $info['name'] ?? 'N/A',
                $info['version'] ?? 'N/A',
                isset($info['enabled']) ? ($info['enabled'] ? '是' : '否') : 'N/A',
                count($info['required_permissions'] ?? []),
                $info['parameter_count'] ?? 0,
            ];
        }

        $this->table($headers, $rows);

        $statistics = $this->documentationGenerator->getGenerationStatistics();
        if (!empty($statistics)) {
            $this->newLine();
            $this->info('統計資訊:');
            $this->line("總Action數量: {$statistics['total_actions']}");
            $this->line("成功生成: {$statistics['successful_generations']}");
            $this->line("生成失敗: {$statistics['failed_generations']}");
            $this->line("生成時間: {$statistics['generation_time']}");
        }

        return 0;
    }

    /**
     * 處理文件驗證
     *
     * @return int
     */
    protected function handleValidation(): int
    {
        $actions = $this->actionRegistry->getAllActions();
        $this->info('開始驗證Action文件...');
        $this->newLine();

        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($actions as $actionType => $actionClass) {
            try {
                $validation = $this->documentationGenerator->validateActionDocumentation($actionType);
                
                $status = $validation['valid'] ? '<info>✓</info>' : '<error>✗</error>';
                $this->line("{$status} {$actionType}");

                if (!empty($validation['errors'])) {
                    foreach ($validation['errors'] as $error) {
                        $this->line("  <error>錯誤:</error> {$error}");
                        $totalErrors++;
                    }
                }

                if (!empty($validation['warnings'])) {
                    foreach ($validation['warnings'] as $warning) {
                        $this->line("  <comment>警告:</comment> {$warning}");
                        $totalWarnings++;
                    }
                }

            } catch (\Exception $e) {
                $this->line("<error>✗</error> {$actionType}");
                $this->line("  <error>錯誤:</error> {$e->getMessage()}");
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info('驗證完成');
        $this->line("總錯誤數: {$totalErrors}");
        $this->line("總警告數: {$totalWarnings}");

        return $totalErrors > 0 ? 1 : 0;
    }

    /**
     * 處理單一Action文件生成
     *
     * @return int
     */
    protected function handleSingleAction(): int
    {
        $actionType = $this->option('action');
        
        if (!$this->actionRegistry->hasAction($actionType)) {
            $this->error("找不到指定的Action: {$actionType}");
            return 1;
        }

        $documentation = $this->documentationGenerator->getActionDocumentation($actionType);
        
        $output = $this->option('output');
        if ($output) {
            File::put($output, json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Action文件已儲存至: {$output}");
        } else {
            $this->line(json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }

    /**
     * 處理完整文件生成
     *
     * @return int
     */
    protected function handleFullDocumentation(): int
    {
        $format = $this->option('format');
        $output = $this->option('output');

        if ($format === 'openapi') {
            $content = $this->documentationGenerator->exportToOpenApi();
            $defaultOutput = 'api-docs.openapi.json';
        } else {
            $documentation = $this->documentationGenerator->generateDocumentation();
            $content = json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $defaultOutput = 'api-docs.json';
        }

        if ($output) {
            File::put($output, $content);
            $this->info("API文件已儲存至: {$output}");
        } else {
            $outputPath = storage_path("app/{$defaultOutput}");
            File::put($outputPath, $content);
            $this->info("API文件已儲存至: {$outputPath}");
        }

        // 顯示統計資訊
        $statistics = $this->documentationGenerator->getGenerationStatistics();
        if (!empty($statistics)) {
            $this->newLine();
            $this->info('生成統計:');
            $this->line("總Action數量: {$statistics['total_actions']}");
            $this->line("成功生成: {$statistics['successful_generations']}");
            $this->line("生成失敗: {$statistics['failed_generations']}");
            $this->line("生成時間: {$statistics['generation_time']}");
        }

        return 0;
    }
}