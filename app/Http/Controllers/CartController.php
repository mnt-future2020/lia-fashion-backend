<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cart = Cart::with(['items.product.colors'])->where('user_id', Auth::id())->first();

        if ($cart) {
            $cart = $cart->toArray();
            $cart['items'] = array_map(function($item) {
                $product = $item['product'];
                $firstColor = collect($product['colors'])->first();
                $coverImage = $firstColor ? $firstColor['cover_image'] : null;

                // Add product image and additional details
                return array_merge($item, [
                    'image' => $coverImage,
                    'product' => array_merge(
                        $product,
                        [
                            'image' => $coverImage,
                            'category_name' => $product['category']['name'] ?? null,
                            'color_info' => collect($product['colors'])
                                ->firstWhere('color', $item['color']) ?? $firstColor
                        ]
                    )
                ]);
            }, $cart['items']);
        }

        return response()->json($cart);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'size' => 'required|string',
            'color' => 'required|string',
            'price' => 'required|numeric'
        ]);

        try {
            $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
            $product = Product::findOrFail($request->product_id);

            // Store original price and discount info
            $originalPrice = $request->price;
            $minQuantityForDiscount = $product->min_quantity_for_discount;
            $bulkDiscountAmount = $product->discounted_price;

            // Calculate effective price
            $effectivePrice = $request->price;
            if ($minQuantityForDiscount &&
                $bulkDiscountAmount &&
                $request->quantity >= $minQuantityForDiscount) {
                $effectivePrice = $originalPrice - $bulkDiscountAmount;
            }

            // Calculate subtotal and tax
            $subtotal = $effectivePrice * $request->quantity;
            $taxPercentage = $product->tax_percentage ?? 0;
            $taxAmount = ($subtotal * $taxPercentage) / 100;

            \Log::info('Creating cart item with:', [
                'min_quantity_for_discount' => $minQuantityForDiscount,
                'bulk_discount_amount' => $bulkDiscountAmount,
                'original_price' => $originalPrice,
                'effective_price' => $effectivePrice
            ]);

            $cartItem = new CartItem([
                'product_id' => $request->product_id,
                'product_name' => $product->name,
                'size' => $request->size,
                'color' => $request->color,
                'quantity' => $request->quantity,
                'unit_price' => $effectivePrice,
                'original_price' => $originalPrice,
                'min_quantity_for_discount' => $minQuantityForDiscount,
                'bulk_discount_amount' => $bulkDiscountAmount, // Make sure this is being set
                'tax_percentage' => $taxPercentage,
                'tax_amount' => $taxAmount,
                'subtotal' => $subtotal
            ]);

            $cart->items()->save($cartItem);
            $this->updateCartTotals($cart);

            return response()->json([
                'message' => 'Product added to cart successfully',
                'cart' => $cart->load('items.product')
            ]);
        } catch (\Exception $e) {
            \Log::error('Cart error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to add item to cart',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateQuantity(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            $cartItem = CartItem::findOrFail($itemId);
            $product = Product::with(['colors'])->findOrFail($cartItem->product_id);
            $cart = $cartItem->cart;

            // Calculate effective price based on quantity threshold
            $effectivePrice = $cartItem->original_price;
            if ($request->quantity >= $cartItem->min_quantity_for_discount) {
                $effectivePrice = $cartItem->original_price - $cartItem->bulk_discount_amount;
            }

            // Update cart item
            $cartItem->quantity = $request->quantity;
            $cartItem->unit_price = $effectivePrice;
            $cartItem->subtotal = $effectivePrice * $request->quantity;
            $cartItem->tax_amount = ($cartItem->subtotal * $cartItem->tax_percentage) / 100;
            $cartItem->save();

            $this->updateCartTotals($cart);

            // Get fresh cart data with images
            $updatedCart = $cart->fresh(['items.product.colors']);

            // Transform cart data to include images
            $updatedCart = $updatedCart->toArray();
            $updatedCart['items'] = array_map(function($item) {
                $product = $item['product'];
                $firstColor = collect($product['colors'])->first();
                $coverImage = $firstColor ? $firstColor['cover_image'] : null;

                return array_merge($item, [
                    'image' => $coverImage,
                    'product' => array_merge(
                        $product,
                        [
                            'image' => $coverImage,
                            'category_name' => $product['category']['name'] ?? null,
                            'color_info' => collect($product['colors'])
                                ->firstWhere('color', $item['color']) ?? $firstColor
                        ]
                    )
                ]);
            }, $updatedCart['items']);

            return response()->json($updatedCart);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update cart'], 500);
        }
    }

    public function removeItem($itemId)
    {
        try {
            $cartItem = CartItem::findOrFail($itemId);
            $cart = $cartItem->cart;
            $cartItem->delete();

            $this->updateCartTotals($cart);

            return response()->json([
                'message' => 'Item removed from cart',
                'cart' => $cart->load('items.product')
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove item'], 500);
        }
    }

    private function updateCartTotals(Cart $cart)
    {
        $totalAmount = 0;
        $totalTax = 0;
        $totalDiscount = 0;

        foreach ($cart->items as $item) {
            // Calculate price based on quantity threshold
            $effectivePrice = $item->original_price;
            if ($item->quantity >= $item->min_quantity_for_discount) {
                $effectivePrice = $item->original_price - $item->bulk_discount_amount;
                $totalDiscount += ($item->original_price - $effectivePrice) * $item->quantity;
            }

            // Calculate totals
            $subtotal = $item->quantity * $effectivePrice;
            $taxAmount = ($subtotal * $item->tax_percentage) / 100;

            // Update item values
            $item->unit_price = $effectivePrice;
            $item->subtotal = $subtotal;
            $item->tax_amount = $taxAmount;
            $item->save();

            $totalAmount += $subtotal;
            $totalTax += $taxAmount;
        }

        // Update cart totals including bulk discounts
        $cart->total_amount = $totalAmount;
        $cart->tax_amount = $totalTax;
        $cart->bulk_discount_total = $totalDiscount;
        $cart->final_amount = $totalAmount + $totalTax - $totalDiscount - ($cart->discount_amount ?? 0);
        $cart->save();
    }

    public function clear()
    {
        try {
            $cart = Cart::where('user_id', Auth::id())->first();
            if ($cart) {
                $cart->items()->delete();
                $cart->delete();
            }
            return response()->json(['message' => 'Cart cleared successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to clear cart'], 500);
        }
    }
}
