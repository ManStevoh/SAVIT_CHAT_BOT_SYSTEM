'use client'

type Product = {
  id: string
  name: string
  description?: string | null
  price: number
}

type Props = {
  company: { name: string; logo?: string | null }
  table: { id: string; name: string; qrToken: string }
  products: Product[]
  slug?: string | null
}

export default function DineInPage({ company, table, products, slug }: Props) {
  return (
    <div className="min-h-screen bg-zinc-50 text-zinc-900">
      <header className="border-b border-zinc-200 bg-white px-4 py-6">
        <p className="text-xs uppercase tracking-[0.2em] text-zinc-500">Dine-in</p>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight">{company.name}</h1>
        <p className="mt-1 text-sm text-zinc-600">Table {table.name}</p>
      </header>

      <main className="mx-auto max-w-lg px-4 py-6">
        {products.length === 0 ? (
          <p className="text-sm text-zinc-600">No menu items available yet.</p>
        ) : (
          <ul className="space-y-3">
            {products.map((product) => (
              <li key={product.id} className="border-b border-zinc-200 pb-3">
                <div className="flex items-baseline justify-between gap-3">
                  <div>
                    <p className="font-medium">{product.name}</p>
                    {product.description ? (
                      <p className="mt-1 text-sm text-zinc-600">{product.description}</p>
                    ) : null}
                  </div>
                  <p className="shrink-0 text-sm font-medium">{product.price.toFixed(2)}</p>
                </div>
              </li>
            ))}
          </ul>
        )}

        {slug ? (
          <a
            href={`/s/${slug}/checkout?table=${encodeURIComponent(table.qrToken)}`}
            className="mt-8 inline-flex w-full items-center justify-center rounded-full bg-zinc-900 px-4 py-3 text-sm font-medium text-white"
          >
            Order for this table
          </a>
        ) : (
          <p className="mt-8 text-sm text-zinc-600">
            Ask staff to enable the online storefront so you can place table orders.
          </p>
        )}
      </main>
    </div>
  )
}
