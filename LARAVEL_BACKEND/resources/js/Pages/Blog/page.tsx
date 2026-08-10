import Link from "next/link"
import useSWR from "swr"
import { ArrowRight, Newspaper } from "lucide-react"
import { LegalLayout } from "@/components/lando/legal-layout"
import { SeoHead, type SeoPayload } from "@/components/seo/SeoHead"
import { apiRequest } from "@/lib/api-client"

type BlogListItem = {
  id: string
  title: string
  slug: string
  excerpt?: string | null
  coverImage?: string | null
  publishedAt?: string | null
}

export default function BlogIndexPage({ seo }: { seo?: SeoPayload | null }) {
  const { data, isLoading } = useSWR<{ posts: BlogListItem[] }>(
    "/api/blog/posts",
    (url: string) => apiRequest(url)
  )

  const posts = data?.posts ?? []

  return (
    <>
      <SeoHead seo={seo} fallbackTitle="Blog — RelayIQ" />
      <LegalLayout
        title="Blog"
        activePath="/blog"
        wide
        plain
        intro={
          <p className="mt-4 max-w-2xl text-base text-muted-foreground sm:text-lg">
            Guides on WhatsApp commerce, AI sales, storefronts, bookings, and growing with RelayIQ.
          </p>
        }
      >
        {isLoading && <p className="text-sm text-muted-foreground">Loading posts…</p>}

        {!isLoading && posts.length === 0 && (
          <div className="rounded-2xl border border-dashed border-border bg-card px-6 py-16 text-center">
            <Newspaper className="mx-auto h-8 w-8 text-muted-foreground" aria-hidden />
            <p className="mt-4 text-sm text-muted-foreground">No posts published yet. Check back soon.</p>
          </div>
        )}

        <div className="grid gap-6 sm:grid-cols-2">
          {posts.map((post) => (
            <article
              key={post.id}
              className="group overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition hover:border-primary/40 hover:shadow-md"
            >
              <Link href={`/blog/${post.slug}`} className="block">
                {post.coverImage ? (
                  <img
                    src={post.coverImage}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                  />
                ) : (
                  <div className="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-primary/15 via-muted to-primary/5">
                    <Newspaper className="h-10 w-10 text-primary/50" aria-hidden />
                  </div>
                )}
                <div className="p-5 sm:p-6">
                  {post.publishedAt ? (
                    <time className="text-xs font-medium text-muted-foreground" dateTime={post.publishedAt}>
                      {new Date(post.publishedAt).toLocaleDateString(undefined, {
                        year: "numeric",
                        month: "long",
                        day: "numeric",
                      })}
                    </time>
                  ) : null}
                  <h2 className="mt-2 text-xl font-bold text-foreground group-hover:text-primary">
                    {post.title}
                  </h2>
                  {post.excerpt ? (
                    <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                      {post.excerpt}
                    </p>
                  ) : null}
                  <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary">
                    Read more
                    <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                  </span>
                </div>
              </Link>
            </article>
          ))}
        </div>
      </LegalLayout>
    </>
  )
}
