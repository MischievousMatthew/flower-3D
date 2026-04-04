<?php
namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorClosedDate;
use App\Models\ReservationAvailabilityCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VendorOrdersController extends Controller
{
    /**
     * Get all orders for the logged-in vendor
     */
    public function getAllOrders(Request $request)
    {
        try {
            $user = $request->user();
            
            // Check if user is a vendor by role
            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }


            Log::info('Fetching orders for vendor', [
                'vendor_id' => $user->id,
                'vendor_name' => $user->name,
                'vendor_data' => $user->vendor_data,
                'filters' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'status' => 'nullable|in:pending,processing,delivered,completed,cancelled,refunded,failed',
                'payment_status' => 'nullable|in:unpaid,paid,refunded,failed',
                'sort_by' => 'nullable|in:reservation_date,created_at,total_amount,status',
                'sort_order' => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:5|max:100',
                'search' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build query with proper relationships for 3D models
            $query = Order::where('vendor_id', $user->id)
                ->with([
                    'user:id,name,email,contact_number,address',
                    'items' => function($query) {
                        $query->with(['product' => function($q) {
                            $q->with(['images', 'models']); // Include product images and 3D models
                        }]);
                    }
                ]);

            // Apply filters
            if ($request->filled('date_from')) {
                $query->whereDate('reservation_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('reservation_date', '<=', $request->date_to);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'reservation_date');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $orders = $query->paginate($perPage);

            Log::info('Orders found', ['count' => $orders->count()]);

            // Format response with proper 3D model data
            $formattedOrders = $orders->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'reservation_date' => $order->reservation_date,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'subtotal' => (float) $order->subtotal,
                    'delivery_fee' => (float) $order->delivery_fee,
                    'total_amount' => (float) $order->total_amount,
                    'customer_notes' => $order->customer_notes,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'user' => [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'contact_number' => $order->user->contact_number,
                        'address' => $order->user->address,
                    ],
                    'customer' => [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'contact_number' => $order->user->contact_number,
                        'address' => $order->user->address,
                    ],
                    'items' => $order->items->map(function($item) {
                        $model3d = $item->product?->models?->first();
                        $modelUrl = $model3d?->model_path
                            ? CloudinaryHelper::getUrl($model3d->model_path, 'raw')
                            : ($model3d?->model_url ?: $item->model_3d_url);
                        
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'product_image' => $item->product_image,
                            'quantity' => (int) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'price' => (float) $item->unit_price, // Alternative field name
                            'subtotal' => (float) $item->subtotal,
                            'product' => $item->product ? [
                                'product_name' => $item->product->product_name,
                                'images' => $item->product->images ?? [],
                                'models' => $item->product->models ? $item->product->models->map(function($model) {
                                    return [
                                        'id' => $model->id,
                                        'model_url' => $model->model_path
                                            ? CloudinaryHelper::getUrl($model->model_path, 'raw')
                                            : $model->model_url,
                                        'model_type' => $model->model_type,
                                        'thumbnail_url' => $model->thumbnail_url,
                                        'metadata' => $model->metadata
                                    ];
                                }) : [],
                            ] : null,
                            'model_3d_url' => $modelUrl,
                            'has_3d_model' => !empty($modelUrl),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $formattedOrders,
                    'pagination' => [
                        'total' => $orders->total(),
                        'per_page' => $orders->perPage(),
                        'current_page' => $orders->currentPage(),
                        'last_page' => $orders->lastPage(),
                        'from' => $orders->firstItem(),
                        'to' => $orders->lastItem(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching vendor orders: ' . $e->getMessage(), [
                'vendor_id' => $request->user()->id ?? 'no user',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get calendar data - returns count per date
     */
    public function getCalendarData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            Log::info('Fetching calendar data', [
                'vendor_id' => $user->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);

            // Get count per date for calendar display
            $calendarData = Order::where('vendor_id', $user->id)
                ->whereBetween('reservation_date', [$request->start_date, $request->end_date])
                ->whereNotNull('reservation_date')
                ->selectRaw('DATE(reservation_date) as date, COUNT(*) as count')
                ->groupBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'count' => (int) $item->count,
                    ];
                });

            Log::info('Calendar data retrieved', [
                'count' => $calendarData->count(),
                'data' => $calendarData->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $calendarData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching calendar data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch calendar data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get order statistics and summary
     */
    public function getOrderStatistics(Request $request)
    {
        try {
            $user = $request->user();
            
            // Check if user is a vendor by role
            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            $vendorId = $user->id;

            // Get date range (default to current month)
            $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
            $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

            $orders = Order::where('vendor_id', $vendorId)
                ->whereBetween('reservation_date', [$dateFrom, $dateTo])
                ->get();

            $statistics = [
                'total_orders' => $orders->count(),
                'total_revenue' => (float) $orders->sum('total_amount'),
                'average_order_value' => $orders->count() > 0 ? (float) $orders->avg('total_amount') : 0,
                'status_breakdown' => [
                    'pending' => $orders->where('status', 'pending')->count(),
                    'processing' => $orders->where('status', 'processing')->count(),
                    'delivered' => $orders->where('status', 'delivered')->count(),
                    'completed' => $orders->where('status', 'completed')->count(),
                    'cancelled' => $orders->where('status', 'cancelled')->count(),
                    'refunded' => $orders->where('status', 'refunded')->count(),
                    'failed' => $orders->where('status', 'failed')->count(),
                ],
                'payment_status_breakdown' => [
                    'unpaid' => $orders->where('payment_status', 'unpaid')->count(),
                    'paid' => $orders->where('payment_status', 'paid')->count(),
                    'refunded' => $orders->where('payment_status', 'refunded')->count(),
                    'failed' => $orders->where('payment_status', 'failed')->count(),
                ],
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order statistics: ' . $e->getMessage(), [
                'vendor_id' => $request->user()->id ?? 'no user',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Get orders for a specific reservation date
     */
    public function getOrdersForDate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            $orders = Order::where('vendor_id', $user->id)
                ->whereDate('reservation_date', $request->date)
                ->with([
                    'user:id,name,email,contact_number,address',
                    'items' => function($query) {
                        $query->with(['product' => function($q) {
                            $q->with(['images', 'models']); // Include 3D models
                        }]);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedOrders = $orders->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'reservation_date' => $order->reservation_date,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'total_amount' => (float) $order->total_amount,
                    'customer_notes' => $order->customer_notes,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'user' => [
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'phone' => $order->user->contact_number,
                        'contact_number' => $order->user->contact_number,
                        'address' => $order->user->address,
                    ],
                    'customer' => [
                        'name' => $order->user->name,
                        'contact_number' => $order->user->contact_number,
                        'address' => $order->user->address,
                    ],
                    'items' => $order->items->map(function($item) {
                        $model3d = $item->product?->models?->first();
                        $modelUrl = $model3d?->model_path
                            ? CloudinaryHelper::getUrl($model3d->model_path, 'raw')
                            : ($model3d?->model_url ?: $item->model_3d_url);
                        
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'product_image' => $item->product_image,
                            'quantity' => (int) $item->quantity,
                            'price' => (float) $item->unit_price,
                            'unit_price' => (float) $item->unit_price,
                            'product' => $item->product ? [
                                'product_name' => $item->product->product_name,
                                'images' => $item->product->images ?? [],
                                'models' => $item->product->models ? $item->product->models->map(function($model) {
                                    return [
                                        'id' => $model->id,
                                        'model_url' => $model->model_path
                                            ? CloudinaryHelper::getUrl($model->model_path, 'raw')
                                            : $model->model_url,
                                        'model_type' => $model->model_type,
                                        'thumbnail_url' => $model->thumbnail_url,
                                        'metadata' => $model->metadata
                                    ];
                                }) : [],
                            ] : null,
                            'model_3d_url' => $modelUrl,
                            'has_3d_model' => !empty($modelUrl),
                        ];
                    }),
                ];
            });

            // Get date statistics
            $stats = [
                'total_orders' => $orders->count(),
                'total_revenue' => (float) $orders->sum('total_amount'),
                'completed_orders' => $orders->where('status', 'completed')->count(),
                'pending_orders' => $orders->where('status', 'pending')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $request->date,
                    'orders' => $formattedOrders,
                    'stats' => $stats,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching orders for date: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders for date',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a single order details
     */
    public function getOrderDetails(Request $request, $orderId)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            // Include product relationship to get all images and 3D models
            $order = Order::where('id', $orderId)
                ->where('vendor_id', $user->id)
                ->with([
                    'user:id,name,email,contact_number,address',
                    'items' => function($query) {
                        $query->with(['product' => function($q) {
                            $q->with(['images', 'models']); // Include all images and 3D models
                        }]);
                    }
                ])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or you do not have permission to view it'
                ], 404);
            }

            $formattedOrder = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'reservation_date' => $order->reservation_date,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => (float) $order->subtotal,
                'delivery_fee' => (float) $order->delivery_fee,
                'total_amount' => (float) $order->total_amount,
                'customer_notes' => $order->customer_notes,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'customer' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'contact_number' => $order->user->contact_number,
                    'address' => $order->user->address,
                ],
                'items' => $order->items->map(function($item) {
                    // Get all product images
                    $productImages = $item->product?->images ?? collect();
                    $allImages = $productImages->map(function($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => $image->image_url,
                            'thumbnail_url' => $image->thumbnail_url ?? $image->image_url,
                            'alt_text' => $image->alt_text ?? '',
                            'sort_order' => $image->sort_order ?? 0,
                        ];
                    })->sortBy('sort_order')->values()->all();
                    
                    $model3d = $item->product?->models?->first();
                    $modelUrl = $model3d?->model_path
                        ? CloudinaryHelper::getUrl($model3d->model_path, 'raw')
                        : ($model3d?->model_url ?: $item->model_3d_url);
                    
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_image' => $item->product_image, // Single main image
                        'product_images' => $allImages, // All product images
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'subtotal' => (float) $item->subtotal,
                        'color' => $item->color,
                        'size' => $item->size,
                        'customizations' => $item->customizations ? json_decode($item->customizations, true) : [],
                        'notes' => $item->notes,
                        'model_3d_url' => $modelUrl,
                        'model_type' => $model3d?->model_type ?? 'glb',
                        'has_3d_model' => !empty($modelUrl),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedOrder,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order details'
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $orderId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,processing,delivered,completed,cancelled,refunded',
            ]);
 
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
 
            $user = $request->user();
 
            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }
 
            $order = Order::where('id', $orderId)
                ->where('vendor_id', $user->id)
                ->with(['items'])
                ->first();
 
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or you do not have permission to update it'
                ], 404);
            }
 
              $oldStatus = $order->status;
              $newStatus = $request->status;

              $allowedTransitions = [
                  'pending' => ['processing', 'cancelled'],
                  'processing' => ['delivered', 'completed', 'cancelled'],
                  'delivered' => ['completed', 'refunded'],
                  'completed' => ['refunded'],
                  'cancelled' => [],
                  'refunded' => [],
              ];

              if ($oldStatus !== $newStatus) {
                  $permitted = $allowedTransitions[$oldStatus] ?? [];

                  if (! in_array($newStatus, $permitted, true)) {
                      return response()->json([
                          'success' => false,
                          'message' => "Cannot update order from {$oldStatus} to {$newStatus}",
                      ], 422);
                  }
              }

              // Route through model helpers so finance/stock logic fires automatically
              if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                  // Deducts stock + credits vendor balance + writes ledger entry
                  $order->markAsCompleted();

              } elseif ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
                  $order->delivered_at = now();
                  $order->status = 'delivered';
                  $order->save();

              } elseif ($newStatus === 'refunded' && $oldStatus !== 'refunded') {
                  // Debits vendor balance + writes refund ledger entry
                  $order->markAsRefunded();
 
            } else {
                // Simple status change — no financial side-effects
                $order->status = $newStatus;
                $order->save();
            }
 
            Log::info('Order status updated', [
                'order_id'   => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'vendor_id'  => $user->id,
            ]);
 
            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'order_id'   => $order->id,
                    'old_status' => $oldStatus,
                    'new_status' => $order->fresh()->status,
                ],
            ]);
 
        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());
 
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status'
            ], 500);
        }
    }

    /**
     * Get upcoming closed dates for the logged-in vendor.
     */
    public function getClosedDates(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            $closedDates = VendorClosedDate::forVendor($user->id)
                ->upcoming()
                ->orderBy('closed_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $closedDates,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching closed dates: ' . $e->getMessage(), [
                'vendor_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load closed dates'
            ], 500);
        }
    }

    /**
     * Mark a reservation date as closed for the logged-in vendor.
     */
    public function markDateAsClosed(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after_or_equal:today',
                'reason' => 'nullable|string|max:255',
                'type' => 'nullable|in:manual,holiday,emergency',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $alreadyClosed = VendorClosedDate::where('vendor_id', $user->id)
                ->whereDate('closed_date', $request->date)
                ->exists();

            if ($alreadyClosed) {
                return response()->json([
                    'success' => false,
                    'message' => 'This date is already marked as closed'
                ], 422);
            }

            $closedDate = VendorClosedDate::create([
                'vendor_id' => $user->id,
                'closed_date' => $request->date,
                'reason' => $request->reason,
                'type' => $request->type ?? 'manual',
            ]);

            ReservationAvailabilityCache::updateForDate($user->id, $request->date);

            return response()->json([
                'success' => true,
                'data' => $closedDate,
                'message' => 'Date marked as closed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking date as closed: ' . $e->getMessage(), [
                'vendor_id' => $request->user()->id ?? null,
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark date as closed'
            ], 500);
        }
    }

    /**
     * Remove a closed date for the logged-in vendor.
     */
    public function removeClosedDate(Request $request, $id)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'vendor') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a vendor'
                ], 403);
            }

            $closedDate = VendorClosedDate::where('id', $id)
                ->where('vendor_id', $user->id)
                ->first();

            if (!$closedDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Closed date not found'
                ], 404);
            }

            $date = $closedDate->closed_date instanceof Carbon
                ? $closedDate->closed_date->format('Y-m-d')
                : Carbon::parse($closedDate->closed_date)->format('Y-m-d');

            $closedDate->delete();
            ReservationAvailabilityCache::updateForDate($user->id, $date);

            return response()->json([
                'success' => true,
                'message' => 'Closed date removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing closed date: ' . $e->getMessage(), [
                'vendor_id' => $request->user()->id ?? null,
                'closed_date_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove closed date'
            ], 500);
        }
    }
}
