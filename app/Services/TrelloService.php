<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TrelloService
{
    protected $client;
    protected $apiKey;
    protected $apiToken;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.trello.api_key');
        $this->apiToken = config('services.trello.api_token');
        $this->baseUrl = config('services.trello.base_url');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30.0,
        ]);
    }

    /**
     * Make a GET request to Trello API with authentication.
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    protected function get($endpoint, $params = [])
    {
        $params['key'] = $this->apiKey;
        $params['token'] = $this->apiToken;

        try {
            $response = $this->client->get($endpoint, [
                'query' => $params,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Trello API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all boards for the authenticated user.
     *
     * @return array
     */
    public function getBoards()
    {
        return $this->get('members/me/boards', [
            'filter' => 'open',
        ]);
    }

    /**
     * Get a specific board by ID.
     *
     * @param string $boardId
     * @return array
     */
    public function getBoard($boardId)
    {
        return $this->get("boards/{$boardId}");
    }

    /**
     * Get all lists for a specific board.
     *
     * @param string $boardId
     * @return array
     */
    public function getBoardLists($boardId)
    {
        return $this->get("boards/{$boardId}/lists", [
            'filter' => 'open',
        ]);
    }

    /**
     * Get all cards for a specific board with full details.
     *
     * @param string $boardId
     * @return array
     */
    public function getBoardCards($boardId)
    {
        return $this->get("boards/{$boardId}/cards", [
            'filter' => 'open',
            'members' => 'true',
            'member_fields' => 'fullName,username',
            'labels' => 'true',
            'customFieldItems' => 'true',
            'pluginData' => 'true',
        ]);
    }

    /**
     * Get all members of a board.
     *
     * @param string $boardId
     * @return array
     */
    public function getBoardMembers($boardId)
    {
        return $this->get("boards/{$boardId}/members", [
            'fields' => 'id,fullName,username',
        ]);
    }

    /**
     * Get all custom fields defined on a board.
     *
     * @param string $boardId
     * @return array
     */
    public function getBoardCustomFields($boardId)
    {
        return $this->get("boards/{$boardId}/customFields");
    }


    /**
     * Generate a comprehensive report for a board.
     *
     * @param string $boardId
     * @param array $filters
     * @return array
     */
    public function generateBoardReport($boardId, $filters = [])
    {
        $lists = $this->getBoardLists($boardId);
        $cards = $this->getBoardCards($boardId);
        $members = $this->getBoardMembers($boardId);
        $customFields = $this->getBoardCustomFields($boardId);

        // Create member lookup
        $memberLookup = [];
        foreach ($members as $member) {
            $memberLookup[$member['id']] = $member;
        }

        // Get list names for grouping
        $listNames = [];
        foreach ($lists as $list) {
            $listNames[$list['id']] = $list['name'];
        }

        // Map custom field IDs to their definitions and build Story Points map
        $customFieldLookup = [];
        $storyPointsMap = [];

        foreach ($customFields as $field) {
            if (!empty($field['id'])) {
                $customFieldLookup[$field['id']] = $field;
            }
        }

        // Build Story Points map from cards' customFieldItems
        foreach ($cards as $card) {
            $cardId = $card['id'] ?? null;
            if (!$cardId || empty($card['customFieldItems'])) {
                continue;
            }

            foreach ($card['customFieldItems'] as $item) {
                $fieldId = $item['idCustomField'] ?? null;
                if (!$fieldId || empty($customFieldLookup[$fieldId])) {
                    continue;
                }

                $field = $customFieldLookup[$fieldId];
                $fieldName = strtolower($field['name'] ?? '');

                // Check if this is a Story Points field
                if ($fieldName === 'story points' || strpos($fieldName, 'story point') !== false) {
                    $value = $item['value'] ?? null;

                    // Direct numeric value
                    if (is_array($value)) {
                        if (isset($value['number']) && is_numeric($value['number'])) {
                            $storyPointsMap[$cardId] = (float) $value['number'];
                            break;
                        }
                        if (isset($value['text']) && is_numeric($value['text'])) {
                            $storyPointsMap[$cardId] = (float) $value['text'];
                            break;
                        }
                    } elseif ($value !== null && is_numeric($value)) {
                        $storyPointsMap[$cardId] = (float) $value;
                        break;
                    }

                    // List/dropdown type - look up option value
                    $optionId = $item['idValue'] ?? null;
                    if ($optionId && !empty($field['options']) && is_array($field['options'])) {
                        foreach ($field['options'] as $option) {
                            if (($option['id'] ?? null) === $optionId) {
                                $optValue = $option['value'] ?? [];
                                if (isset($optValue['number']) && is_numeric($optValue['number'])) {
                                    $storyPointsMap[$cardId] = (float) $optValue['number'];
                                    break 2;
                                }
                                if (isset($optValue['text']) && is_numeric($optValue['text'])) {
                                    $storyPointsMap[$cardId] = (float) $optValue['text'];
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Apply filters FIRST, before any grouping
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $cards = $this->filterCardsByDate($cards, $filters, $customFieldLookup);
        }

        // Filter by assignees (members)
        if (!empty($filters['assignees'])) {
            $cards = $this->filterCardsByAssignees($cards, $filters['assignees']);
        }

        // Filter by lists (columns)
        if (!empty($filters['lists'])) {
            $cards = $this->filterCardsByLists($cards, $filters['lists']);
        }

        // Group cards by list AFTER filtering
        $cardsByList = [];
        foreach ($cards as $card) {
            $listId = $card['idList'];
            if (!isset($cardsByList[$listId])) {
                $cardsByList[$listId] = [];
            }
            $cardsByList[$listId][] = $card;
        }

        // Group cards by member with details
        // Only include selected assignees if filters are applied
        $selectedAssigneeIds = !empty($filters['assignees']) ? $filters['assignees'] : null;

        $cardsByMember = [];
        $memberStats = [];
        foreach ($cards as $card) {
            $cardPoints = $this->extractPoints($card, $customFieldLookup, $storyPointsMap);
            if (!empty($card['idMembers'])) {
                foreach ($card['idMembers'] as $memberId) {
                    // If assignees filter is set, only include selected assignees
                    if ($selectedAssigneeIds !== null && !in_array($memberId, $selectedAssigneeIds)) {
                        continue;
                    }

                    if (!isset($cardsByMember[$memberId])) {
                        $cardsByMember[$memberId] = [];
                        $memberStats[$memberId] = [
                            'name' => $memberLookup[$memberId]['fullName'] ?? 'Unknown',
                            'username' => $memberLookup[$memberId]['username'] ?? '',
                            'card_count' => 0,
                            'total_points' => 0,
                        ];
                    }
                    $cardsByMember[$memberId][] = $card;
                    $memberStats[$memberId]['card_count']++;
                    $memberStats[$memberId]['total_points'] += $cardPoints;
                }
            } else {
                // Cards without members - only include if no assignee filter is set
                if ($selectedAssigneeIds === null) {
                    if (!isset($cardsByMember['unassigned'])) {
                        $cardsByMember['unassigned'] = [];
                        $memberStats['unassigned'] = [
                            'name' => 'Unassigned',
                            'username' => '',
                            'card_count' => 0,
                            'total_points' => 0,
                        ];
                    }
                    $cardsByMember['unassigned'][] = $card;
                    $memberStats['unassigned']['card_count']++;
                    $memberStats['unassigned']['total_points'] += $cardPoints;
                }
            }
        }

        // If assignees filter is set, ensure all selected assignees appear in stats (even with 0 cards)
        if ($selectedAssigneeIds !== null) {
            foreach ($selectedAssigneeIds as $memberId) {
                if (!isset($memberStats[$memberId])) {
                    $memberStats[$memberId] = [
                        'name' => $memberLookup[$memberId]['fullName'] ?? 'Unknown',
                        'username' => $memberLookup[$memberId]['username'] ?? '',
                        'card_count' => 0,
                        'total_points' => 0,
                    ];
                }
            }
        }

        // Calculate card status breakdown
        $statusBreakdown = [
            'total' => count($cards),
            'completed' => 0,
            'in_progress' => 0,
            'todo' => 0,
            'other' => 0,
        ];

        foreach ($cards as $card) {
            $listId = $card['idList'];
            $rawListName = $listNames[$listId] ?? '';
            $statusKey = $this->classifyStatusByListName($rawListName);

            if (isset($statusBreakdown[$statusKey])) {
                $statusBreakdown[$statusKey]++;
            } else {
                $statusBreakdown['other']++;
            }
        }

        // Build cards by list with list names and points
        $cardsByListWithNames = [];
        $totalPoints = 0;
        foreach ($cardsByList as $listId => $listCards) {
            $listPoints = 0;
            foreach ($listCards as $card) {
                $listPoints += $this->extractPoints($card, $customFieldLookup, $storyPointsMap);
            }

            $cardsByListWithNames[$listNames[$listId] ?? $listId] = [
                'list_id' => $listId,
                'list_name' => $listNames[$listId] ?? 'Unknown',
                'card_count' => count($listCards),
                'total_points' => $listPoints,
                'cards' => $listCards,
            ];
        }

        // Calculate total points from all cards
        foreach ($cards as $card) {
            $totalPoints += $this->extractPoints($card, $customFieldLookup, $storyPointsMap);
        }

        // Enhanced card details with member names and dates
        $enhancedCards = [];
        foreach ($cards as $card) {
            $cardMemberNames = [];
            if (!empty($card['idMembers'])) {
                foreach ($card['idMembers'] as $memberId) {
                    if (isset($memberLookup[$memberId])) {
                        $cardMemberNames[] = $memberLookup[$memberId]['fullName'] ?? $memberLookup[$memberId]['username'] ?? 'Unknown';
                    }
                }
            }

            $severity = $this->extractSeverityKey($card, $customFieldLookup);
            $severityMultiplier = $this->severityMultiplier($severity);

            $enhancedCards[] = [
                'id' => $card['id'],
                'name' => $card['name'],
                'description' => $card['desc'] ?? '',
                'url' => $card['url'] ?? '',
                'list_id' => $card['idList'],
                'list_name' => $listNames[$card['idList']] ?? 'Unknown',
                'members' => $cardMemberNames,
                'member_ids' => $card['idMembers'] ?? [],
                'labels' => $card['labels'] ?? [],
                'points' => $this->extractPoints($card, $customFieldLookup, $storyPointsMap),
                'severity' => $severity,
                'severity_multiplier' => $severityMultiplier,
                'due_date' => !empty($card['due']) ? date('Y-m-d H:i:s', strtotime($card['due'])) : null,
                'due_complete' => $card['dueComplete'] ?? false,
                'date_last_activity' => !empty($card['dateLastActivity']) ? date('Y-m-d H:i:s', strtotime($card['dateLastActivity'])) : null,
                'date_completed' => $this->extractDateCompletedDay($card, $customFieldLookup),
                'created_date' => $this->extractCreatedDate($card['id'] ?? ''),
                'status_key' => $this->classifyStatusByListName($listNames[$card['idList']] ?? ''),
            ];
        }

        return [
            'board_id' => $boardId,
            'total_cards' => count($cards),
            'total_lists' => count($lists),
            'total_points' => $totalPoints,
            'cards_by_list' => $cardsByListWithNames,
            'cards_by_member' => $cardsByMember,
            'member_stats' => $memberStats,
            'status_breakdown' => $statusBreakdown,
            'lists' => $lists,
            'members' => $members,
            'cards' => $enhancedCards,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Classify card status based on the Trello list name.
     *
     * @param string $listName
     * @return string one of: completed, in_progress, todo, other
     */
    protected function classifyStatusByListName($listName)
    {
        $normalized = strtolower(trim($listName));

        // Explicit lists that should always be treated as completed
        $explicitCompletedLists = [
            'for dev deployment/review (tiger/jan review)',
            'for dev deployment/review',
            'on dev environment',
            'on staging / demo to po',
            'on live',
            'done / archive',
            'done/archive',
            'done/archived',
            'done sprint',
            'archive done',
        ];

        // Explicit lists for in-progress
        $explicitInProgressLists = [
            'in dev',
        ];

        // Explicit lists for todo
        $explicitTodoLists = [
            'current sprint',
        ];

        if (in_array($normalized, $explicitCompletedLists, true) ||
            // Handles variants like "On Live🎉"
            str_starts_with($normalized, 'on live') ||
            // Pipeline columns from board (e.g. "For Dev Deployment/Review", "For Staging Deployment/Review (PO review LANCE)")
            str_contains($normalized, 'for dev deployment/review') ||
            str_contains($normalized, 'for staging deployment/review') ||
            strpos($normalized, 'done') !== false ||
            strpos($normalized, 'complete') !== false ||
            strpos($normalized, 'finished') !== false) {
            return 'completed';
        }

        if (in_array($normalized, $explicitInProgressLists, true) ||
            strpos($normalized, 'progress') !== false ||
            strpos($normalized, 'doing') !== false ||
            strpos($normalized, 'in progress') !== false) {
            return 'in_progress';
        }

        if (in_array($normalized, $explicitTodoLists, true) ||
            strpos($normalized, 'todo') !== false ||
            strpos($normalized, 'to do') !== false ||
            strpos($normalized, 'backlog') !== false) {
            return 'todo';
        }

        return 'other';
    }

    /**
     * Extract created date from card ID.
     *
     * @param string $cardId
     * @return string|null
     */
    protected function extractCreatedDate($cardId)
    {
        if (empty($cardId)) {
            return null;
        }

        $hexTimestamp = substr($cardId, 0, 8);
        $timestamp = hexdec($hexTimestamp);

        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * Extract points from card.
     *
     * Priority:
     * 1. Story Points map (derived from customFieldItems)
     * 2. Plugin data (Story Points power-up)
     * 3. Custom fields from card's customFieldItems array
     * 4. Labels like \"5pts\", \"10 points\"
     * 5. Card name like \"[5pts] Task name\"
     *
     * @param array $card
     * @param array $customFieldLookup
     * @param array $storyPointsMap Map of card ID => story points value
     * @return float
     */
    protected function extractPoints($card, $customFieldLookup = [], $storyPointsMap = [])
    {
        $points = 0;
        $cardId = $card['id'] ?? null;

        // 1) Check Story Points map first (most reliable)
        if ($cardId && !empty($storyPointsMap[$cardId])) {
            return (float) $storyPointsMap[$cardId];
        }

        // 2) Story Points from pluginData (Scrum / Story Points power-ups)
        if (!empty($card['pluginData']) && is_array($card['pluginData'])) {
            foreach ($card['pluginData'] as $pluginDatum) {
                $raw = $pluginDatum['value'] ?? null;
                if (!is_string($raw) || $raw === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE &&
                    isset($decoded['points']) &&
                    is_numeric($decoded['points'])) {
                    return (float) $decoded['points'];
                }
            }
        }

        // 3) Trello Story Points from custom fields (fallback)
        if (!empty($card['customFieldItems']) && !empty($customFieldLookup)) {
            foreach ($card['customFieldItems'] as $item) {
                $fieldId = $item['idCustomField'] ?? null;
                if (!$fieldId || empty($customFieldLookup[$fieldId])) {
                    continue;
                }

                $field = $customFieldLookup[$fieldId];
                $fieldName = strtolower($field['name'] ?? '');

                // Match fields like "Story Points", "Points", etc.
                if (strpos($fieldName, 'story point') !== false || $fieldName === 'points' || $fieldName === 'story points') {
                    $value = $item['value'] ?? null;

                    // Case 1: numeric value stored directly on the card (number or text)
                    if (is_array($value)) {
                        if (isset($value['number']) && is_numeric($value['number'])) {
                            $points = (float) $value['number'];
                            break;
                        }

                        if (isset($value['text']) && is_numeric($value['text'])) {
                            $points = (float) $value['text'];
                            break;
                        }
                    }

                    // Case 2: list-type Story Points field, value selected via idValue → option
                    $optionId = $item['idValue'] ?? null;
                    if ($optionId && !empty($field['options']) && is_array($field['options'])) {
                        foreach ($field['options'] as $option) {
                            if (($option['id'] ?? null) !== $optionId) {
                                continue;
                            }

                            $optValue = $option['value'] ?? [];
                            if (isset($optValue['number']) && is_numeric($optValue['number'])) {
                                $points = (float) $optValue['number'];
                                break 2; // found our story points
                            }

                            if (isset($optValue['text']) && is_numeric($optValue['text'])) {
                                $points = (float) $optValue['text'];
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        // 4) Fallback: check labels for points (e.g., "5pts", "10 points")
        if ($points === 0 && !empty($card['labels'])) {
            foreach ($card['labels'] as $label) {
                $labelName = strtolower($label['name'] ?? '');
                if (preg_match('/(\d+)\s*(?:pts?|points?)/i', $labelName, $matches)) {
                    $points += (int) $matches[1];
                }
            }
        }

        // 5) Fallback: check card name for points (e.g., "[5pts] Task name")
        if ($points === 0 && !empty($card['name'])) {
            if (preg_match('/\[(\d+)\s*(?:pts?|points?)\]/i', $card['name'], $matches)) {
                $points += (int) $matches[1];
            }
        }

        return $points;
    }

    /**
     * Filter cards by date range.
     *
     * Default: a card is included if **any** of: due date, last activity, custom "Date Completed",
     * or card creation (id) falls within the range.
     *
     * When {@see $filters}['date_completed_only'] is true: include only cards whose **Date Completed**
     * custom field (date type, name contains "date completed") falls within the range; cards
     * without that field set are excluded.
     *
     * @param array $cards
     * @param array $filters
     * @param array $customFieldLookup id => field definition from getBoardCustomFields
     * @return array
     */
    protected function filterCardsByDate($cards, $filters, array $customFieldLookup = [])
    {
        $filtered = [];
        $dateFromDay = !empty($filters['date_from']) ? substr((string) $filters['date_from'], 0, 10) : null;
        $dateToDay = !empty($filters['date_to']) ? substr((string) $filters['date_to'], 0, 10) : null;
        $dateFrom = $dateFromDay ? strtotime($dateFromDay . ' 00:00:00') : null;
        $dateTo = $dateToDay ? strtotime($dateToDay . ' 23:59:59') : null;
        $dateCompletedOnly = !empty($filters['date_completed_only']);

        foreach ($cards as $card) {
            if ($dateCompletedOnly) {
                $completedDay = $this->extractDateCompletedDay($card, $customFieldLookup);
                if (!$completedDay) {
                    continue;
                }
                if ($dateFromDay && $completedDay < $dateFromDay) {
                    continue;
                }
                if ($dateToDay && $completedDay > $dateToDay) {
                    continue;
                }
                $filtered[] = $card;

                continue;
            }

            $timestamps = [];

            if (!empty($card['due'])) {
                $t = strtotime($card['due']);
                if ($t) {
                    $timestamps[] = $t;
                }
            }
            if (!empty($card['dateLastActivity'])) {
                $t = strtotime($card['dateLastActivity']);
                if ($t) {
                    $timestamps[] = $t;
                }
            }
            $completedAt = $this->extractDateCompletedTimestamp($card, $customFieldLookup);
            if ($completedAt) {
                $timestamps[] = $completedAt;
            }
            if (!empty($card['id'])) {
                $hexTimestamp = substr($card['id'], 0, 8);
                $timestamps[] = hexdec($hexTimestamp);
            }

            $timestamps = array_values(array_unique(array_filter($timestamps)));

            if (($dateFrom || $dateTo) && $timestamps === []) {
                continue;
            }

            $inRange = false;
            foreach ($timestamps as $cardDate) {
                if ($dateFrom && $cardDate < $dateFrom) {
                    continue;
                }
                if ($dateTo && $cardDate > $dateTo) {
                    continue;
                }
                $inRange = true;
                break;
            }

            if (!$inRange) {
                continue;
            }

            $filtered[] = $card;
        }

        return $filtered;
    }

    /**
     * Calendar date (Y-m-d) from the board's "Date Completed" date custom field.
     *
     * @param array $card
     * @param array $customFieldLookup
     * @return string|null
     */
    protected function extractDateCompletedDay(array $card, array $customFieldLookup): ?string
    {
        if (empty($card['customFieldItems']) || $customFieldLookup === []) {
            return null;
        }

        foreach ($card['customFieldItems'] as $item) {
            if (!$this->isDateCompletedCustomFieldItem($item, $customFieldLookup)) {
                continue;
            }

            $value = $item['value'] ?? null;
            if (!is_array($value) || empty($value['date'])) {
                continue;
            }

            $raw = (string) $value['date'];
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
                return $m[1];
            }

            $t = strtotime($raw);

            return $t ? date('Y-m-d', $t) : null;
        }

        return null;
    }

    /**
     * Unix timestamp from Date Completed (for inclusive date-range mode).
     *
     * @param array $card
     * @param array $customFieldLookup
     * @return int|null
     */
    protected function extractDateCompletedTimestamp(array $card, array $customFieldLookup): ?int
    {
        $day = $this->extractDateCompletedDay($card, $customFieldLookup);

        return $day ? strtotime($day . ' 12:00:00') : null;
    }

    /**
     * @param array $item customFieldItem from Trello card
     * @param array $customFieldLookup
     */
    protected function isDateCompletedCustomFieldItem(array $item, array $customFieldLookup): bool
    {
        $fieldId = $item['idCustomField'] ?? null;
        if (!$fieldId || empty($customFieldLookup[$fieldId])) {
            return false;
        }

        $field = $customFieldLookup[$fieldId];
        $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
        if ($fieldType !== 'date') {
            return false;
        }

        $fieldName = strtolower(trim((string) ($field['name'] ?? '')));

        return $fieldName === 'date completed';
    }

    /**
     * Extract severity key (P1..P4) from a Trello custom field named "Severity".
     * Supports dropdown/list fields (idValue -> option.value.text).
     *
     * @param array $card Raw card from Trello API
     * @param array $customFieldLookup id => field definition from getBoardCustomFields()
     * @return string|null One of P1, P2, P3, P4
     */
    protected function extractSeverityKey(array $card, array $customFieldLookup): ?string
    {
        if (empty($card['customFieldItems']) || $customFieldLookup === []) {
            return null;
        }

        foreach ($card['customFieldItems'] as $item) {
            $fieldId = $item['idCustomField'] ?? null;
            if (!$fieldId || empty($customFieldLookup[$fieldId])) {
                continue;
            }

            $field = $customFieldLookup[$fieldId];
            $fieldName = strtolower(trim((string) ($field['name'] ?? '')));
            if ($fieldName !== 'severity') {
                continue;
            }

            $idValue = $item['idValue'] ?? null;
            if (!$idValue || empty($field['options']) || !is_array($field['options'])) {
                continue;
            }

            foreach ($field['options'] as $opt) {
                if (($opt['id'] ?? null) !== $idValue) {
                    continue;
                }

                $txt = strtoupper(trim((string) (($opt['value']['text'] ?? '') ?: '')));
                if (in_array($txt, ['P1', 'P2', 'P3', 'P4'], true)) {
                    return $txt;
                }
            }
        }

        return null;
    }

    /**
     * KPI multiplier for severity. Defaults to P4 (1.0) when missing/unknown.
     */
    protected function severityMultiplier(?string $severity): float
    {
        $key = strtoupper(trim((string) $severity));
        switch ($key) {
            case 'P1':
                return 1.3;
            case 'P2':
                return 1.2;
            case 'P3':
                return 1.1;
            case 'P4':
            default:
                return 1.0;
        }
    }

    /**
     * Filter cards by assignees.
     *
     * @param array $cards
     * @param array $assigneeIds
     * @return array
     */
    protected function filterCardsByAssignees($cards, $assigneeIds)
    {
        if (empty($assigneeIds)) {
            return $cards;
        }

        $filtered = [];
        foreach ($cards as $card) {
            $cardMemberIds = $card['idMembers'] ?? [];
            if (!empty(array_intersect($cardMemberIds, $assigneeIds))) {
                $filtered[] = $card;
            }
        }

        return $filtered;
    }

    /**
     * Filter cards by Trello lists / columns.
     *
     * @param array $cards
     * @param array $listIds
     * @return array
     */
    protected function filterCardsByLists($cards, $listIds)
    {
        if (empty($listIds)) {
            return $cards;
        }

        $filtered = [];
        foreach ($cards as $card) {
            if (!empty($card['idList']) && in_array($card['idList'], $listIds)) {
                $filtered[] = $card;
            }
        }

        return $filtered;
    }
}
