<?php

namespace App\Http\Controllers;

use App\Models\OrderRequest;
use App\Services\ReturnCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/** The customer's side of a return/refund case */
class CustomerReturnCaseController extends Controller
{
    public function __construct(private ReturnCaseService $cases) {}

    /** GET /api/customer/return-cases/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $case = $this->find($request, $id);

        return $case
            ? response()->json(['success' => true, 'data' => $this->cases->detail($case)])
            : response()->json(['success' => false, 'message' => 'Request not found'], 404);
    }

    /** POST /api/customer/return-cases/{id}/{action} */
    public function act(Request $request, int $id, string $action): JsonResponse
    {
        $case = $this->find($request, $id);
        if (!$case) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }
        $actor = $this->cases->customerActor($request->user());

        try {
            [$case, $message] = match ($action) {
                'accept' => [$this->cases->accept($case, $actor), 'Agreed. The shop will take it from here.'],
                'offer' => $this->offer($request, $case, $actor),
                'withdraw' => [$this->cases->withdraw($case, $actor, $request->input('note')), 'Request withdrawn.'],
                'message' => $this->message($request, $case, $actor),
                'confirm-refund' => [$this->cases->confirmRefund($case, $actor), 'Thanks for confirming. The case is closed.'],
                'refund-missing' => [$this->cases->refundMissing($case, $actor, $request->input('note')), "We've told the shop you haven't received it."],
                'confirm-replacement' => [$this->cases->confirmReplacement($case, $actor), 'Thanks for confirming. The case is closed.'],
                'replacement-missing' => [$this->cases->replacementMissing($case, $actor, $request->input('note')), "We've told the shop you haven't received it."],
                default => throw new RuntimeException('Unknown action.'),
            };
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $this->cases->detail($case)]);
    }

    private function offer(Request $request, OrderRequest $case, array $actor): array
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:full_refund,partial_refund,replacement'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return [$this->cases->offer($case, $actor, $data['resolution'], isset($data['amount']) ? (float) $data['amount'] : null, $data['note'] ?? null), 'Counter-offer sent to the shop.'];
    }

    private function message(Request $request, OrderRequest $case, array $actor): array
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:20480'],
        ]);

        return [$this->cases->message($case, $actor, $data['body'], $request->file('media')), 'Message sent.'];
    }

    private function find(Request $request, int $id): ?OrderRequest
    {
        return OrderRequest::query()->where('user_id', $request->user()->id)->find($id);
    }
}
