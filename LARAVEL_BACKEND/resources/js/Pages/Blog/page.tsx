import Link from "next/link"
import useSWR from "swr"
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
      <LegalLayout title="Blog" activePath="/blog">
        <p className="!mt-0 text-base text-gray-600">
          Guides on WhatsApp commerce, AI sales, and growing with RelayIQ.
        </p>

        {isLoading && <p className="mt-8 text-sm text-gray-500">Loading posts…</p>}

        {!isLoading && posts.length === 0 && (
          <p className="mt-8 text-sm text-gray-500">No posts published yet. Check back soon.</p>
        )}

        <div className="mt-10 space-y-8 not-prose">
          {posts.map((post) => (
            <article key={post.id} className="border-b border-gray-200 pb-8 last:border-0">
              <Link href={`/blog/${post.slug}`} className="group block">
                {post.coverImage ? (
                  <img
                    src={post.coverImage}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="mb-4 aspect-[2/1] w-full rounded-xl object-cover"
                  />
                ) : null}
                <h2 className="text-xl font-bold text-black group-hover:text-[#2563eb] sm:text-2xl">
                  {post.title}
                </h2>
                {post.publishedAt ? (
                  <time className="mt-2 block text-xs text-gray-500" dateTime={post.publishedAt}>
                    {new Date(post.publishedAt).toLocaleDateString(undefined, {
                      year: "numeric",
                      month: "long",
                      day: "numeric",
                    })}
                  </time>
                ) : null}
                {post.excerpt ? <p className="mt-3 text-sm leading-relaxed text-gray-600">{post.excerpt}</p> : null}
                <span className="mt-3 inline-block text-sm font-medium text-[#2563eb]">Read more →</span>
              </Link>
            </article>
          ))}
        </div>
      </LegalLayout>
    </>
  )
}
