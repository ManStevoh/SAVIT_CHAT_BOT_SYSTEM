import Link from "next/link"
import useSWR from "swr"
import { LegalLayout } from "@/components/lando/legal-layout"
import { SeoHead, type SeoPayload } from "@/components/seo/SeoHead"
import { apiRequest } from "@/lib/api-client"

type BlogPost = {
  id: string
  title: string
  slug: string
  excerpt?: string | null
  body: string
  coverImage?: string | null
  publishedAt?: string | null
}

export default function BlogShowPage({
  slug,
  seo,
}: {
  slug: string
  seo?: SeoPayload | null
}) {
  const { data, isLoading, error } = useSWR<{ post: BlogPost }>(
    slug ? `/api/blog/posts/${slug}` : null,
    (url: string) => apiRequest(url)
  )

  const post = data?.post

  return (
    <>
      <SeoHead seo={seo} fallbackTitle={post?.title || "Blog — RelayIQ"} />
      <LegalLayout title={post?.title || "Blog"} activePath="/blog">
        <p className="!mt-0">
          <Link href="/blog" className="text-sm font-medium text-[#2563eb] hover:underline">
            ← All posts
          </Link>
        </p>

        {isLoading && <p className="mt-6 text-sm text-gray-500">Loading…</p>}
        {error && <p className="mt-6 text-sm text-red-600">Post not found.</p>}

        {post && (
          <>
            {post.publishedAt ? (
              <time className="mt-2 block text-xs text-gray-500" dateTime={post.publishedAt}>
                {new Date(post.publishedAt).toLocaleDateString(undefined, {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })}
              </time>
            ) : null}
            {post.coverImage ? (
              <img
                src={post.coverImage}
                alt=""
                loading="eager"
                decoding="async"
                className="mt-6 aspect-[2/1] w-full rounded-xl object-cover not-prose"
              />
            ) : null}
            <div className="mt-8" dangerouslySetInnerHTML={{ __html: post.body }} />
          </>
        )}
      </LegalLayout>
    </>
  )
}
