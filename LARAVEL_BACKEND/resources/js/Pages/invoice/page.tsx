'use client'

type OrderItem = { name: string; quantity: number; price: number; lineSubtotal: number }
type OrderPayload = {
  orderNumber: string
  customerName: string
  customerPhone?: string | null
  deliveryAddress?: string | null
  fulfillmentType: string
  paymentStatus: string
  subtotal: number
  taxTotal: number
  deliveryFee: number
  total: number
  totalFormatted: string
  createdAt?: string | null
  items: OrderItem[]
}

type Props = {
  token: string
  order: OrderPayload
  company: { name: string; logo?: string | null }
}

export default function PublicInvoicePage({ order, company }: Props) {
  return (
    <div className="min-h-screen bg-slate-50 px-4 py-12 print:bg-white">
      <div className="mx-auto max-w-2xl space-y-6 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm print:border-0 print:shadow-none">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-xl font-semibold">{company.name}</h1>
            <p className="text-sm text-slate-500">Invoice #{order.orderNumber}</p>
            {order.createdAt && (
              <p className="text-sm text-slate-500">{new Date(order.createdAt).toLocaleDateString()}</p>
            )}
          </div>
          <span
            className={`rounded-full px-3 py-1 text-xs font-medium capitalize ${
              order.paymentStatus === 'paid'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-amber-100 text-amber-700'
            }`}
          >
            {order.paymentStatus}
          </span>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 text-sm">
          <div>
            <p className="font-medium text-slate-700">Bill to</p>
            <p className="text-slate-600">{order.customerName}</p>
            {order.customerPhone && <p className="text-slate-600">{order.customerPhone}</p>}
          </div>
          <div>
            <p className="font-medium text-slate-700">Fulfillment</p>
            <p className="capitalize text-slate-600">{order.fulfillmentType.replace('_', ' ')}</p>
            {order.deliveryAddress && <p className="text-slate-600">{order.deliveryAddress}</p>}
          </div>
        </div>

        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-200 text-left text-slate-500">
              <th className="py-2 font-medium">Item</th>
              <th className="py-2 text-right font-medium">Qty</th>
              <th className="py-2 text-right font-medium">Price</th>
              <th className="py-2 text-right font-medium">Total</th>
            </tr>
          </thead>
          <tbody>
            {order.items.map((item, idx) => (
              <tr key={idx} className="border-b border-slate-100">
                <td className="py-2">{item.name}</td>
                <td className="py-2 text-right">{item.quantity}</td>
                <td className="py-2 text-right">{item.price.toFixed(2)}</td>
                <td className="py-2 text-right">{item.lineSubtotal.toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="ml-auto max-w-xs space-y-1 text-sm">
          <div className="flex justify-between text-slate-600">
            <span>Subtotal</span>
            <span>{order.subtotal.toFixed(2)}</span>
          </div>
          {order.taxTotal > 0 && (
            <div className="flex justify-between text-slate-600">
              <span>Tax</span>
              <span>{order.taxTotal.toFixed(2)}</span>
            </div>
          )}
          {order.deliveryFee > 0 && (
            <div className="flex justify-between text-slate-600">
              <span>Delivery</span>
              <span>{order.deliveryFee.toFixed(2)}</span>
            </div>
          )}
          <div className="flex justify-between border-t border-slate-200 pt-1 text-base font-semibold">
            <span>Total</span>
            <span>{order.totalFormatted}</span>
          </div>
        </div>
      </div>
    </div>
  )
}
